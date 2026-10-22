/* MenuDash guest menu: language switch, diet filters, sticky category tabs and the photo view.
   The specials box and the holiday notice ([menudash_specials], [menudash_closed], data-lite)
   have no bar, so the filter and tab code below does nothing there.
   The page already holds every language (see includes/render.php); this only flips
   attributes, so the menu still reads fine if the script never runs. */
(function () {
  "use strict";

  var LANG_KEY = "menudash-lang";
  var VIEWS = ["all", "en", "de", "zh"];
  var HTML_LANG = { en: "en", de: "de", zh: "zh-Hant" };

  function browserUi() {
    var n = (navigator.language || "").slice(0, 2);
    if (n === "zh") return "zh";
    return n === "de" || n === "fr" || n === "it" ? "de" : "en";
  }

  function store(key, value) {
    try { localStorage.setItem(key, value); } catch (e) { /* private mode */ }
  }

  /* Keep ?lang= and ?diet= in the address, so a shared link opens the same view. */
  function setParam(name, value) {
    try {
      var url = new URL(location.href);
      if (value) url.searchParams.set(name, value); else url.searchParams.delete(name);
      history.replaceState(history.state, "", url);
    } catch (e) { /* file:// in some browsers */ }
  }

  function init(root) {
    var bar = root.querySelector(".mdash-bar");
    var chips = Array.prototype.slice.call(root.querySelectorAll(".mdash-chip"));
    var langButtons = Array.prototype.slice.call(root.querySelectorAll("[data-set-lang]"));
    var sections = Array.prototype.slice.call(root.querySelectorAll(".mdash-section"));
    var tabs = Array.prototype.slice.call(root.querySelectorAll(".mdash-cats a"));
    var nav = root.querySelector(".mdash-cats");
    var noMatch = root.querySelector(".mdash-nomatch");
    var chipBox = root.querySelector(".mdash-chips");
    var filtered = root.querySelector(".mdash-filtered");
    var dialog = root.querySelector(".mdash-dlg:not(.mdash-list-dlg)"); // The photo; the guest's list has its own.
    if (!bar && !root.hasAttribute("data-lite")) return; // No menu uploaded yet.

    /* ---------- "Name / 中文" that doesn't fit ----------
       Where the Chinese name would wrap to the next line (which then starts with "/"),
       give it a line of its own instead. Measured again after every change of width or
       language: first clear, then read all positions, then mark, to keep layout cheap. */
    var names = Array.prototype.slice.call(root.querySelectorAll(".mdash-dish .mdash-name"));
    function fitNames() {
      names.forEach(function (h) { h.classList.remove("mdash-own"); });
      var wrapped = names.filter(function (h) {
        var zh = h.querySelector(".mdash-zh");
        if (!zh || getComputedStyle(zh).display === "none" || h.offsetParent === null) return false;
        var first = null;
        Array.prototype.some.call(h.querySelectorAll(":scope > .mdash-t:not(.mdash-zh)"), function (t) {
          if (getComputedStyle(t).display !== "none") first = t;
          return !!first;
        });
        var a = first && first.getClientRects()[0], b = zh.getClientRects()[0];
        return a && b && b.top > a.top + 4;
      });
      wrapped.forEach(function (h) { h.classList.add("mdash-own"); });
      edges();
    }

    /* ---------- Filter row wider than the screen: fade the side that has more ---------- */
    function edges() {
      if (!chipBox) return;
      var x = chipBox.scrollLeft, more = chipBox.scrollWidth - chipBox.clientWidth;
      chipBox.classList.toggle("mdash-more-l", x > 2);
      chipBox.classList.toggle("mdash-more-r", x < more - 2);
    }
    if (chipBox) chipBox.addEventListener("scroll", edges, { passive: true });

    /* ---------- Language ---------- */
    function setLang(view, remember) {
      if (VIEWS.indexOf(view) < 0) view = "all";
      root.setAttribute("data-lang", view);
      root.setAttribute("data-ui", view === "all" ? browserUi() : view);
      if (view === "all") root.removeAttribute("lang"); else root.setAttribute("lang", HTML_LANG[view]);
      langButtons.forEach(function (b) {
        b.setAttribute("aria-pressed", String(b.getAttribute("data-set-lang") === view));
      });
      if (remember) {
        store(LANG_KEY, view);
        setParam("lang", view === "all" ? "" : view);
        // The other MenuDash boxes on the page (holiday notice, specials) follow along.
        Array.prototype.forEach.call(document.querySelectorAll(".menudash"), function (other) {
          if (other !== root && other.mdashSetLang) other.mdashSetLang(view, false);
        });
      }
      fitNames();
      measure();
    }
    langButtons.forEach(function (b) {
      b.addEventListener("click", function () { setLang(b.getAttribute("data-set-lang"), true); });
    });
    root.mdashSetLang = setLang;
    setLang(root.getAttribute("data-lang"), false);

    /* ---------- Lunch menu: "See the whole week" opens the folded days in place ---------- */
    var more = root.querySelector(".mdash-lu-more");
    if (more) {
      more.hidden = false;
      more.addEventListener("click", function () {
        var open = !root.classList.toggle("mdash-lu-folded");
        more.setAttribute("aria-expanded", String(open));
        if (!open && root.getBoundingClientRect().top < 0) scrollToEl(root); // Back to today, not left mid-week.
      });
    }

    /* ---------- Diet filters ----------
       Every pressed chip must match (AND). Each dish lists its tokens in data-f:
       pick, veg (vegetarian or vegan), vegan, gf, lf (lactose-free or vegan), and spicy or mild.
       Spicy and Not spicy exclude each other, so pressing one releases the other. */
    var opposite = { spicy: "mild", mild: "spicy" };
    function release(key) {
      chips.forEach(function (o) { if (o.getAttribute("data-filter") === opposite[key]) o.setAttribute("aria-pressed", "false"); });
    }
    function active() {
      return chips.filter(function (c) { return c.getAttribute("aria-pressed") === "true"; })
        .map(function (c) { return c.getAttribute("data-filter"); });
    }
    function applyFilters(remember) {
      if (!bar) return;
      var need = active();
      var shown = 0;
      sections.forEach(function (sec) {
        var inSec = 0;
        Array.prototype.forEach.call(sec.querySelectorAll(".mdash-dish"), function (d) {
          var has = " " + d.getAttribute("data-f") + " ";
          var ok = need.every(function (t) { return has.indexOf(" " + t + " ") >= 0; });
          d.hidden = !ok;
          if (ok) inSec++;
        });
        sec.hidden = inSec === 0;
        var tab = nav.querySelector('[data-sec="' + sec.getAttribute("data-sec") + '"]');
        if (tab) tab.hidden = inSec === 0;
        shown += inSec;
      });
      noMatch.hidden = shown > 0;
      showFiltered(need, shown);
      if (remember) setParam("diet", need.join(","));
      fitNames();
      spy();
    }
    /* While filtering, a line under the buttons: "18 of 110 dishes · Vegetarian" in each
       interface language, and "Show all" to turn every filter off. */
    var total = root.querySelectorAll(".mdash-section .mdash-dish").length;
    var shownText = filtered ? Array.prototype.slice.call(filtered.querySelectorAll(".mdash-shown .mdash-u")) : [];
    shownText.forEach(function (s) { s.setAttribute("data-tpl", s.textContent); });
    function showFiltered(need, shown) {
      if (!filtered) return;
      filtered.hidden = need.length === 0;
      if (!need.length) return;
      shownText.forEach(function (s) {
        var l = s.getAttribute("data-l");
        var names = chips.filter(function (c) { return c.getAttribute("aria-pressed") === "true"; }).map(function (c) {
          var t = c.querySelector('.mdash-u[data-l="' + l + '"]');
          return (t || c).textContent.trim();
        });
        s.textContent = s.getAttribute("data-tpl").replace("%1$s", shown).replace("%2$s", total) + " · " + names.join(l === "zh" ? "、" : ", ");
      });
    }
    function clearFilters() {
      chips.forEach(function (c) { c.setAttribute("aria-pressed", "false"); });
      applyFilters(true);
    }
    if (filtered) filtered.querySelector(".mdash-clear").addEventListener("click", function () {
      clearFilters();
      if (chipBox) chipBox.scrollLeft = 0;
    });

    chips.forEach(function (c) {
      c.addEventListener("click", function () {
        var on = c.getAttribute("aria-pressed") !== "true";
        c.setAttribute("aria-pressed", String(on));
        if (on) release(c.getAttribute("data-filter"));
        applyFilters(true);
      });
    });
    if (bar) root.querySelector(".mdash-reset").addEventListener("click", clearFilters);
    try {
      var diet = (new URLSearchParams(location.search).get("diet") || "").split(",");
      chips.forEach(function (c) {
        if (diet.indexOf(c.getAttribute("data-filter")) >= 0) c.setAttribute("aria-pressed", "true");
      });
      if (diet.indexOf("mild") >= 0) release("mild"); // a link with both keeps Not spicy
    } catch (e) { /* old browser: start unfiltered */ }

    /* ---------- Sticky bar: sit under the theme's fixed header ----------
       Looks for fixed or sticky page elements covering the top edge (the admin bar, a
       sticky site header) and stacks below them. offset="120" in the shortcode skips this. */
    var autoOffset = root.getAttribute("data-offset") === "auto";
    var lastTop = -1;
    function headerBottom() {
      var bottom = 0;
      for (var pass = 0; pass < 4; pass++) {
        var y = bottom + 1;
        if (y >= innerHeight / 2) break;
        var found = 0;
        var els = document.elementsFromPoint(innerWidth / 2, y);
        for (var i = 0; i < els.length && !found; i++) {
          if (root.contains(els[i])) continue;
          for (var e = els[i]; e && e !== document.body && e !== document.documentElement; e = e.parentElement) {
            var pos = getComputedStyle(e).position;
            if (pos === "fixed" || pos === "sticky") {
              var r = e.getBoundingClientRect();
              if (r.top <= y && r.bottom > bottom && r.height < innerHeight / 3) found = r.bottom;
              break;
            }
          }
        }
        if (!found || found <= bottom) break;
        bottom = found;
      }
      return Math.round(bottom);
    }
    function measure() {
      if (!bar) return;
      if (autoOffset) {
        var top = headerBottom();
        if (top !== lastTop) {
          root.style.setProperty("--mdash-top", top + "px");
          lastTop = top;
        }
      }
      root.style.setProperty("--mdash-bar-h", Math.round(bar.getBoundingClientRect().height) + "px");
      // "Stuck" while the bar is held at the top over the scrolling menu (themes style it then):
      // it has moved away from what is above it (the language buttons), more than their margin.
      var above = bar.previousElementSibling;
      while (above && !above.getClientRects().length) above = above.previousElementSibling;
      var stuck = !!above && bar.getBoundingClientRect().top > above.getBoundingClientRect().bottom + (parseFloat(getComputedStyle(above).marginBottom) || 0) + 2;
      if (stuck !== bar.classList.contains("mdash-stuck")) bar.classList.toggle("mdash-stuck", stuck);
    }

    /* ---------- Category tabs follow the reading position ---------- */
    var current = null;
    function spy() {
      if (!bar) return;
      var line = (parseFloat(getComputedStyle(root).getPropertyValue("--mdash-top")) || 0) + bar.offsetHeight + 24;
      var pick = null;
      for (var i = 0; i < sections.length; i++) {
        if (sections[i].hidden) continue;
        if (sections[i].getBoundingClientRect().top <= line) pick = sections[i];
        else break;
      }
      var shown = sections.filter(function (s) { return !s.hidden; });
      if (!pick) pick = shown[0] || null;
      // At the very bottom the last categories can't scroll up to the bar; the last one
      // on screen is the one the guest is looking at (or just tapped).
      var doc = document.documentElement;
      var last = shown[shown.length - 1];
      if (last && innerHeight + (window.scrollY || doc.scrollTop) >= doc.scrollHeight - 4 && last.getBoundingClientRect().top < innerHeight) pick = last;
      var id = pick ? pick.getAttribute("data-sec") : null;
      if (id === current) return;
      current = id;
      tabs.forEach(function (t) {
        var on = t.getAttribute("data-sec") === id;
        t.classList.toggle("mdash-on", on);
        if (on) {
          t.setAttribute("aria-current", "true");
          // Scroll only the tab strip, never the page.
          var left = t.offsetLeft - (nav.clientWidth - t.offsetWidth) / 2;
          if (nav.scrollTo) nav.scrollTo({ left: left, behavior: "smooth" }); else nav.scrollLeft = left;
        } else {
          t.removeAttribute("aria-current");
        }
      });
    }
    /* A tab scrolls its category to just under the bar. Done here rather than by the
       #anchor, because a theme or the admin bar can add scroll-padding to the page. */
    tabs.forEach(function (t) {
      t.addEventListener("click", function (ev) {
        var sec = root.querySelector('.mdash-section[data-sec="' + t.getAttribute("data-sec") + '"]');
        if (!sec) return;
        ev.preventDefault();
        measure();
        var gap = (parseFloat(getComputedStyle(root).getPropertyValue("--mdash-top")) || 0) + bar.offsetHeight + 4;
        var y = sec.getBoundingClientRect().top + (window.scrollY || document.documentElement.scrollTop) - gap;
        var smooth = !(window.matchMedia && matchMedia("(prefers-reduced-motion: reduce)").matches);
        window.scrollTo({ top: Math.max(0, y), behavior: smooth ? "smooth" : "auto" });
        try { history.replaceState(history.state, "", "#" + sec.id); } catch (e) { /* file:// */ }
      });
    });

    var ticking = false;
    function onScroll() {
      if (ticking) return;
      ticking = true;
      requestAnimationFrame(function () {
        ticking = false;
        measure();
        spy();
      });
    }
    addEventListener("scroll", onScroll, { passive: true });
    addEventListener("resize", onScroll);

    /* ---------- Photo, large ---------- */
    if (dialog && dialog.showModal) {
      var big = dialog.querySelector("img");
      var cap = dialog.querySelector(".mdash-cap");
      root.addEventListener("click", function (ev) {
        var btn = ev.target.closest ? ev.target.closest("button.mdash-photo") : null;
        if (!btn) return;
        var dish = btn.closest(".mdash-dish");
        big.src = btn.getAttribute("data-full");
        dialog.classList.toggle("mdash-square", btn.classList.contains("mdash-square"));
        cap.innerHTML = "";
        cap.appendChild(dish.querySelector(".mdash-name").cloneNode(true));
        // The guest's list: "+ Add to my list" under the photo, for a dish that has a +.
        var addHere = dialog.querySelector(".mdash-dlg-add");
        var plus = dish.querySelector(".mdash-add[data-add]");
        if (addHere) {
          addHere.hidden = !plus || !root.classList.contains("mdash-has-list");
          addHere.setAttribute("data-add", plus ? plus.getAttribute("data-add") : "");
        }
        dialog.showModal();
      });
      // A click on the dimmed backdrop closes it too (the dialog itself is the target
      // then, as it is for its padding, hence the position check).
      dialog.addEventListener("click", function (ev) {
        var r = dialog.getBoundingClientRect();
        if (ev.target === dialog && (ev.clientX < r.left || ev.clientX > r.right || ev.clientY < r.top || ev.clientY > r.bottom)) dialog.close();
      });
      dialog.addEventListener("close", function () { big.removeAttribute("src"); });
    }

    var resizing = 0;
    addEventListener("resize", function () {
      cancelAnimationFrame(resizing);
      resizing = requestAnimationFrame(fitNames);
    });

    applyFilters(false);
    fitNames();
    measure();
    spy();
    // Fonts change line lengths and the bar's height once they arrive.
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(function () { fitNames(); measure(); spy(); });
  }

  /* ---------- Fixed page parts (theme header, admin bar, phone action bar) ----------
     How far fixed or sticky elements outside MenuDash cover the top and the bottom edge,
     so jumps land below a sticky header and the back-to-top button sits above a bottom bar. */
  function coveredAt(y, fromTop) {
    var edge = fromTop ? 0 : innerHeight;
    var els = document.elementsFromPoint(innerWidth / 2, y);
    for (var i = 0; i < els.length; i++) {
      if (els[i].closest(".menudash, .mdash-totop, .mdash-listbar")) continue; // MenuDash's own bars don't count.
      for (var e = els[i]; e && e !== document.body && e !== document.documentElement; e = e.parentElement) {
        var pos = getComputedStyle(e).position;
        if (pos === "fixed" || pos === "sticky") {
          var r = e.getBoundingClientRect();
          if (r.height < innerHeight / 3) edge = fromTop ? Math.max(edge, r.bottom) : Math.min(edge, r.top);
          break;
        }
      }
    }
    return fromTop ? edge : innerHeight - edge;
  }
  /* How far from the bottom edge fixed bars reach: looked for at the very edge and a little
     above it, for a bar that floats above the edge (a theme's pill-shaped phone bar). */
  function bottomCover() {
    return Math.max(coveredAt(innerHeight - 2, false), coveredAt(innerHeight - 30, false));
  }
  function scrollToEl(el) {
    var smooth = !(matchMedia && matchMedia("(prefers-reduced-motion: reduce)").matches);
    var y = el.getBoundingClientRect().top + scrollY - coveredAt(1, true) - 12;
    scrollTo({ top: Math.max(0, y), behavior: smooth ? "smooth" : "auto" });
  }

  /* ---------- Jump buttons: [Mittagsmenü] [Heute empfohlen] [Speisekarte] ----------
     With two or more MenuDash boxes on the page (lunch, specials, menu), a row of buttons
     above the first one leads to each, with the language switch beside them. Each box brings
     its own label (.mdash-jump-label, in the three languages); the row is itself a MenuDash
     box, so it follows the language like the others. */
  function jumpNav() {
    var boxes = Array.prototype.filter.call(document.querySelectorAll(".menudash[data-jump]"), function (b) {
      return b.querySelector(".mdash-jump-label");
    });
    // The empty row the inline script of the second box kept free (render.php).
    var slot = document.querySelector(".mdash-jump-slot");
    if (boxes.length < 2) {
      if (slot) slot.parentNode.removeChild(slot);
      return null;
    }
    var first = boxes[0];
    var nav = document.createElement("nav");
    nav.className = "menudash mdash-jumpnav";
    nav.setAttribute("data-lite", "");
    nav.setAttribute("data-lang", first.getAttribute("data-lang") || "all");
    nav.setAttribute("data-ui", first.getAttribute("data-ui") || "de");
    var menuLabel = document.querySelector(".menudash[data-jump='menu'] .mdash-jump-label");
    if (menuLabel && menuLabel.getAttribute("data-nav")) nav.setAttribute("aria-label", menuLabel.getAttribute("data-nav"));
    var links = document.createElement("div");
    links.className = "mdash-jump-links";
    boxes.forEach(function (box) {
      var a = document.createElement("a");
      a.className = "mdash-jump-link";
      a.href = "#" + box.id;
      a.innerHTML = box.querySelector(".mdash-jump-label").innerHTML + ' <span aria-hidden="true">↓</span>';
      a.addEventListener("click", function (ev) { ev.preventDefault(); scrollToEl(box); });
      links.appendChild(a);
    });
    nav.appendChild(links);
    // The language switch of the menu (or of any box), copied: one switch for the whole page.
    var langs = document.querySelector(".menudash[data-jump='menu'] .mdash-langs") || document.querySelector(".menudash[data-jump] .mdash-langs");
    if (langs) nav.appendChild(langs.cloneNode(true));
    if (slot) slot.parentNode.replaceChild(nav, slot);
    else first.parentNode.insertBefore(nav, first);
    init(nav);
    return nav;
  }

  /* ---------- Back to top: a small button while reading the long menu ---------- */
  function backToTop(target) {
    var menu = document.querySelector(".menudash[data-jump='menu']");
    var label = menu && menu.querySelector(".mdash-jump-label");
    if (!menu || !label) return;
    var btn = document.createElement("button");
    btn.type = "button";
    btn.className = "mdash-totop";
    btn.hidden = true;
    btn.setAttribute("aria-label", label.getAttribute("data-top") || "↑");
    btn.innerHTML = '<span aria-hidden="true">↑</span>';
    // Outside the MenuDash boxes, so it takes the owner's colours from the menu.
    var cs = getComputedStyle(menu);
    btn.style.setProperty("--mdash-accent", cs.getPropertyValue("--mdash-accent"));
    btn.style.setProperty("--mdash-on-accent", cs.getPropertyValue("--mdash-on-accent"));
    btn.addEventListener("click", function () { scrollToEl(target || menu); });
    document.body.appendChild(btn);
    var busy = false;
    function update() {
      busy = false;
      // From one screen into the menu on, until its end.
      var r = menu.getBoundingClientRect();
      var show = r.top < -innerHeight * 0.6 && r.bottom > innerHeight * 0.5;
      if (show) btn.style.bottom = bottomCover() + 12 + "px";
      btn.hidden = !show;
    }
    addEventListener("scroll", function () { if (!busy) { busy = true; requestAnimationFrame(update); } }, { passive: true });
    addEventListener("resize", update);
  }

  /* ---------- The guest's list (MenuDash → Menu → Guest list) ----------
     The + beside a dish puts it on a list kept in this browser tab only (sessionStorage), to
     show the staff when ordering: number, name in the guest's language with the German one
     under it for the staff, how many, and a mark for a dish the guest has a question about. Nothing is sent from here; MenuDash Statistics counts the
     taps on its own. The bar that opens the list is fixed, so it is moved out of the menu box
     (a container query box would hold it), like the back-to-top button. */
  var LIST_KEY = "menudash-list";
  function guestList(root) {
    var dlg = root.querySelector(".mdash-list-dlg");
    var bar = root.querySelector(".mdash-listbar");
    // The menu's dishes, and those of the specials and lunch boxes on this page ("sp:", "lu:").
    var adds = Array.prototype.slice.call(document.querySelectorAll(".menudash .mdash-add[data-add]"));
    if (!dlg || !bar || !dlg.showModal) return; // The + buttons stay invisible (menu.css).
    var byKey = Object.create(null); // No inherited names ("constructor") from a tampered stored list.
    adds.forEach(function (b) { byKey[b.getAttribute("data-add")] = b; });
    var ol = dlg.querySelector(".mdash-list-items");
    var empty = dlg.querySelector(".mdash-list-empty");
    var totalBox = dlg.querySelector(".mdash-list-total");
    var priceNote = dlg.querySelector(".mdash-list-price");
    var labels = dlg.querySelector(".mdash-list-labels");
    function words(name) {
      try { return JSON.parse(labels.getAttribute("data-" + name)); } catch (e) { return {}; }
    }
    var more = words("more"), less = words("less"), ask = words("ask"), qword = words("qword"), remove = words("remove");
    var sure = {};
    try { sure = JSON.parse(dlg.getAttribute("data-sure")); } catch (e) { /* plain confirm */ }

    // [{ k: dish key, n: how many, q: 1 when the guest has a question, d: what the list shows of
    // the dish }], in the order added. The list follows the guest from page to page (the specials
    // on the home page, the menu on its own page), so each dish keeps what the list shows of it;
    // a dish on this page gets it fresh from its row.
    var items = [];
    function str(v) { return typeof v === "string" ? v.slice(0, 200) : ""; }
    try {
      var saved = JSON.parse(sessionStorage.getItem(LIST_KEY) || "[]");
      if (Array.isArray(saved)) items = saved.filter(function (x) {
        return x && typeof x.k === "string" && x.k.length <= 120 && x.n >= 1 && (byKey[x.k] || (x.d && typeof x.d === "object"));
      }).slice(0, 100).map(function (x) {
        var d = byKey[x.k] ? snapshot(x.k) : { no: str(x.d.no), de: str(x.d.de), en: str(x.d.en), zh: str(x.d.zh), price: str(x.d.price), measure: str(x.d.measure) };
        return { k: x.k, n: Math.min(99, Math.floor(x.n)), q: x.q ? 1 : 0, d: d };
      }).filter(function (x) { return x.d.de || x.d.en || x.d.zh; });
    } catch (e) { /* no storage: the list lasts until the page is left */ }
    function save() {
      try { sessionStorage.setItem(LIST_KEY, JSON.stringify(items)); } catch (e) { /* private mode */ }
    }
    function find(k) {
      for (var i = 0; i < items.length; i++) if (items[i].k === k) return items[i];
      return null;
    }
    function ui() { return root.getAttribute("data-ui") || "de"; }
    function say(map) { return map[ui()] || map.de || ""; }

    // What the list keeps of a dish, read from its row: number, its name in each language (as
    // each view shows it), price.
    function snapshot(k) {
      var li = byKey[k].closest(".mdash-dish");
      var name = li.querySelector(".mdash-name");
      var no = li.querySelector(".mdash-no");
      var price = li.querySelector(".mdash-price");
      function inView(v) {
        var t = name.querySelector(':scope > .mdash-t[data-l~="' + v + '"]:not(.mdash-zh)');
        return t ? t.textContent.replace(/\s+/g, " ").trim() : "";
      }
      var small = price && price.querySelector("small");
      return {
        no: no ? no.textContent.trim() : "",
        de: inView("de"), en: inView("en"), zh: inView("zh"),
        price: price ? (price.firstChild ? price.firstChild.textContent : price.textContent).trim() : "",
        measure: small ? small.textContent.trim() : ""
      };
    }
    // What the list shows of a dish now: the guest's language first; under it the German for
    // the staff. Showing all languages: German first and 中文 and English under it, as on the menu.
    function dish(it) {
      var s = it.d;
      var view = root.getAttribute("data-lang");
      var first = view === "all" || view === "de" ? "de" : view;
      var main = s[first] || s.de || s.en || s.zh;
      var subs = [];
      (view === "all" ? ["zh", "en"] : first !== "de" ? ["de"] : []).forEach(function (l) {
        var x = s[l];
        if (x && x !== main && !subs.some(function (y) { return y.text === x; })) subs.push({ text: x, lang: HTML_LANG[l] });
      });
      return { no: s.no, name: main, lang: HTML_LANG[s[first] ? first : "de"], subs: subs, price: s.price, measure: s.measure };
    }

    function el(tag, cls, text) {
      var e = document.createElement(tag);
      if (cls) e.className = cls;
      if (text != null) e.textContent = text;
      return e;
    }
    function button(cls, text, label) {
      var b = el("button", cls, text);
      b.type = "button";
      b.setAttribute("aria-label", label);
      return b;
    }

    function render() {
      ol.innerHTML = "";
      var sum = 0, sums = true;
      items.forEach(function (it) {
        var d = dish(it);
        var li = el("li", "mdash-li" + (it.q ? " mdash-li-asked" : ""));
        li.setAttribute("data-k", it.k);
        li.appendChild(el("span", "mdash-li-no", d.no));
        var nm = el("div", "mdash-li-name");
        var strong = el("strong", "", d.name);
        strong.setAttribute("lang", d.lang);
        nm.appendChild(strong);
        d.subs.forEach(function (s) {
          var sub = el("span", "mdash-li-own", s.text);
          sub.setAttribute("lang", s.lang);
          nm.appendChild(sub);
        });
        var q = el("span", "mdash-li-q", say(qword));
        nm.appendChild(q);
        li.appendChild(nm);
        var ctl = el("div", "mdash-li-ctl");
        var a = button("mdash-li-ask", say(qword), say(ask) + ": " + d.name);
        a.setAttribute("aria-pressed", String(!!it.q));
        ctl.appendChild(a);
        ctl.appendChild(button("mdash-li-less", "−", say(less) + ": " + d.name));
        ctl.appendChild(el("span", "mdash-li-n", String(it.n)));
        ctl.appendChild(button("mdash-li-more", "+", say(more) + ": " + d.name));
        li.appendChild(ctl);
        li.appendChild(el("span", "mdash-li-price", d.price + (d.measure ? " " + d.measure : "")));
        li.appendChild(button("mdash-li-del", "×", say(remove) + ": " + d.name));
        ol.appendChild(li);
        // A total only when every price is a plain number ("12", "23.5").
        if (/^\d+(\.\d+)?$/.test(d.price) && !d.measure) sum += it.n * parseFloat(d.price);
        else sums = false;
      });
      empty.hidden = items.length > 0;
      totalBox.hidden = !items.length || !sums;
      priceNote.hidden = totalBox.hidden; // "A guide only": the bill is what is ordered.
      totalBox.querySelector("strong").textContent = String(parseFloat(sum.toFixed(2)));
      dlg.querySelector(".mdash-list-clear").hidden = !items.length;
    }

    function update() {
      var count = 0;
      adds.forEach(function (b) {
        var it = find(b.getAttribute("data-add"));
        b.classList.toggle("mdash-in", !!it);
        b.querySelector(".mdash-add-n").textContent = it ? String(it.n) : "";
      });
      items.forEach(function (it) { count += it.n; });
      bar.hidden = count === 0;
      bar.querySelector(".mdash-listbar-n").textContent = String(count);
      place();
      if (dlg.open) render();
      save();
    }

    function change(k, by) {
      var it = find(k);
      if (!it && by > 0 && byKey[k]) items.push(it = { k: k, n: 0, q: 0, d: snapshot(k) });
      if (!it) return;
      it.n = Math.min(99, it.n + by);
      if (it.n < 1) items.splice(items.indexOf(it), 1);
      update();
    }

    adds.forEach(function (b) {
      b.addEventListener("click", function () {
        change(b.getAttribute("data-add"), 1);
        bar.classList.remove("mdash-bump");
        void bar.offsetWidth; // Restart the little bounce.
        bar.classList.add("mdash-bump");
      });
    });
    ol.addEventListener("click", function (ev) {
      var b = ev.target.closest ? ev.target.closest("button") : null;
      var row = b && b.closest(".mdash-li");
      if (!row) return;
      var k = row.getAttribute("data-k");
      if (b.classList.contains("mdash-li-more")) change(k, 1);
      else if (b.classList.contains("mdash-li-less")) change(k, -1);
      else if (b.classList.contains("mdash-li-del")) {
        var gone = find(k);
        if (gone) change(k, -gone.n);
        var next = ol.querySelector(".mdash-li-del") || dlg.querySelector(".mdash-close");
        if (next) next.focus(); // The row is gone: on to the next one, or to close.
        return;
      }
      else if (b.classList.contains("mdash-li-ask")) {
        var it = find(k);
        if (it) { it.q = it.q ? 0 : 1; update(); }
      }
      // The row was drawn again: keep the keyboard on the same button.
      // Found by walking the rows, not by a selector built from the key (no CSS.escape needed).
      var again = null;
      Array.prototype.some.call(ol.children, function (li) {
        if (li.getAttribute("data-k") === k) again = li.querySelector("." + b.className.split(" ")[0]);
        return li.getAttribute("data-k") === k;
      });
      if (again) again.focus();
    });
    // "+ Add to my list" in a photo view: add the dish, close the photo, back to the menu.
    document.addEventListener("click", function (ev) {
      var b = ev.target.closest ? ev.target.closest(".mdash-dlg-add[data-add]") : null;
      var k = b && b.getAttribute("data-add");
      if (!k || !byKey[k]) return;
      change(k, 1);
      var photo = b.closest("dialog");
      if (photo && photo.open) photo.close();
      bar.classList.remove("mdash-bump");
      void bar.offsetWidth;
      bar.classList.add("mdash-bump");
    });
    dlg.querySelector(".mdash-list-clear").addEventListener("click", function () {
      if (!confirm(say(sure) || "?")) return;
      items = [];
      update();
      dlg.close();
    });
    dlg.addEventListener("click", function (ev) {
      var r = dlg.getBoundingClientRect();
      if (ev.target === dlg && (ev.clientX < r.left || ev.clientX > r.right || ev.clientY < r.top || ev.clientY > r.bottom)) dlg.close();
    });

    // The bar: out of the menu box, in the menu's colours and interface language.
    document.body.appendChild(bar);
    var cs = getComputedStyle(root);
    ["--mdash-accent", "--mdash-on-accent", "--mdash-sans"].forEach(function (p) { bar.style.setProperty(p, cs.getPropertyValue(p)); });
    function place() {
      // Not while the list is open: the page under it can't be measured then.
      if (!bar.hidden && !dlg.open) bar.style.bottom = bottomCover() + 12 + "px";
    }
    bar.addEventListener("click", function () {
      render();
      dlg.showModal();
    });
    var busy = false;
    addEventListener("scroll", function () { if (!busy) { busy = true; requestAnimationFrame(function () { busy = false; place(); }); } }, { passive: true });
    addEventListener("resize", place);
    // A new language: the bar's words and the names on the list follow.
    function lang() {
      bar.setAttribute("data-ui", ui());
      if (dlg.open) render();
    }
    if (window.MutationObserver) new MutationObserver(lang).observe(root, { attributes: true, attributeFilter: ["data-ui", "data-lang"] });
    lang();
    adds.forEach(function (b) { b.closest(".menudash").classList.add("mdash-has-list"); });
    update();
  }

  function start() {
    // A box with data-own-js (the gift card form of MenuDash Gift Cards) runs its own script.
    Array.prototype.forEach.call(document.querySelectorAll(".menudash"), function (root) {
      if (!root.hasAttribute("data-own-js") && !root.classList.contains("mdash-jump-slot")) init(root);
    });
    var nav = jumpNav();
    backToTop(nav);
    // The guest's list lives in the first box that brought it (menu, specials or lunch).
    var listBox = document.querySelector(".menudash .mdash-list-dlg");
    if (listBox) guestList(listBox.closest(".menudash"));
  }
  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", start);
  else start();
})();
