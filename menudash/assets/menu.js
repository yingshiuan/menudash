/* MenuDash guest menu: language switch, diet filters, sticky category tabs and the photo view.
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
    var dialog = root.querySelector(".mdash-dlg");
    if (!bar) return; // No menu uploaded yet.

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
    }

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
      }
      fitNames();
      measure();
    }
    langButtons.forEach(function (b) {
      b.addEventListener("click", function () { setLang(b.getAttribute("data-set-lang"), true); });
    });
    setLang(root.getAttribute("data-lang"), false);

    /* ---------- Diet filters ----------
       Every pressed chip must match (AND). Each dish lists its tokens in data-f:
       pick, veg (vegetarian or vegan), vegan, gf, and spicy or mild. */
    function active() {
      return chips.filter(function (c) { return c.getAttribute("aria-pressed") === "true"; })
        .map(function (c) { return c.getAttribute("data-filter"); });
    }
    function applyFilters(remember) {
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
      if (remember) setParam("diet", need.join(","));
      fitNames();
      spy();
    }
    chips.forEach(function (c) {
      c.addEventListener("click", function () {
        c.setAttribute("aria-pressed", String(c.getAttribute("aria-pressed") !== "true"));
        applyFilters(true);
      });
    });
    root.querySelector(".mdash-reset").addEventListener("click", function () {
      chips.forEach(function (c) { c.setAttribute("aria-pressed", "false"); });
      applyFilters(true);
    });
    try {
      var diet = (new URLSearchParams(location.search).get("diet") || "").split(",");
      chips.forEach(function (c) {
        if (diet.indexOf(c.getAttribute("data-filter")) >= 0) c.setAttribute("aria-pressed", "true");
      });
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
      if (autoOffset) {
        var top = headerBottom();
        if (top !== lastTop) {
          root.style.setProperty("--mdash-top", top + "px");
          lastTop = top;
        }
      }
      root.style.setProperty("--mdash-bar-h", Math.round(bar.getBoundingClientRect().height) + "px");
    }

    /* ---------- Category tabs follow the reading position ---------- */
    var current = null;
    function spy() {
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

  function start() {
    Array.prototype.forEach.call(document.querySelectorAll(".menudash"), init);
  }
  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", start);
  else start();
})();
