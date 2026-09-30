/* MenuDash admin page: upload photos one at a time, so no single request is ever
   bigger than one photo, and shrink a photo in the browser first when it is over the
   server's limit. */

/* Drop areas for the single-file choosers (menu CSV, specials CSV, each diet icon): a file
   dropped on the box goes into its file input, as if it had been chosen. */
(function () {
  "use strict";
  Array.prototype.forEach.call(document.querySelectorAll("[data-drop]"), function (box) {
    var input = box.querySelector('input[type="file"]');
    if (!input) return;
    // Boxes with their own file-name line (the CSV boxes) show the chosen file there.
    var name = box.querySelector(".mdash-drop-name");
    if (name) {
      input.addEventListener("change", function () {
        var f = input.files && input.files[0];
        name.textContent = f ? f.name : name.getAttribute("data-empty");
        box.classList.toggle("has-file", !!f);
      });
    }
    ["dragenter", "dragover"].forEach(function (t) {
      box.addEventListener(t, function (e) { e.preventDefault(); box.classList.add("over"); });
    });
    ["dragleave", "drop"].forEach(function (t) {
      box.addEventListener(t, function (e) {
        if (t === "dragleave" && box.contains(e.relatedTarget)) return;
        box.classList.remove("over");
      });
    });
    box.addEventListener("drop", function (e) {
      e.preventDefault();
      var files = e.dataTransfer && e.dataTransfer.files;
      if (!files || !files.length) return;
      try {
        var dt = new DataTransfer();
        dt.items.add(files[0]);
        input.files = dt.files;
      } catch (err) {
        input.files = files; // Older Safari: no DataTransfer constructor.
      }
      input.dispatchEvent(new Event("change", { bubbles: true }));
    });
  });
})();

(function () {
  "use strict";
  var cfg = window.menudashAdmin;
  var input = document.getElementById("mdash-photos");
  if (!cfg || !input) return;

  var box = document.getElementById("mdash-progress");
  var bar = box.querySelector(".mdash-meter span");
  var status = box.querySelector(".mdash-status");
  var log = box.querySelector(".mdash-log");
  var drop = input.closest(".mdash-drop");
  var busy = false;
  // The words written here, in the owner's language (includes/admin.php), English when missing.
  var I18N = window.menudashAdminI18n || {};
  function t(k, en) {
    var s = I18N[k] || en, args = Array.prototype.slice.call(arguments, 2);
    return s.replace(/%(?:(\d)\$)?s/g, function (m, n) { return String(n ? args[n - 1] : args.shift()); });
  }

  function line(text, cls) {
    var li = document.createElement("li");
    li.textContent = text;
    if (cls) li.className = cls;
    log.appendChild(li);
    return li;
  }

  /* Over the limit: redraw at most 1400 px wide as WebP (or PNG where the browser can't
     write WebP). Both keep the transparent background of the cut-outs. */
  function shrink(file) {
    return new Promise(function (resolve, reject) {
      var img = new Image();
      var url = URL.createObjectURL(file);
      img.onload = function () {
        var scale = Math.min(1, 1400 / Math.max(img.naturalWidth, img.naturalHeight));
        var c = document.createElement("canvas");
        c.width = Math.round(img.naturalWidth * scale);
        c.height = Math.round(img.naturalHeight * scale);
        c.getContext("2d").drawImage(img, 0, 0, c.width, c.height);
        URL.revokeObjectURL(url);
        c.toBlob(function (blob) {
          if (blob && blob.type === "image/webp") return resolve(blob);
          c.toBlob(function (png) { png ? resolve(png) : reject(new Error(t("shrinkFail", "could not shrink"))); }, "image/png");
        }, "image/webp", 0.92);
      };
      img.onerror = function () { URL.revokeObjectURL(url); reject(new Error(t("notImage", "not an image"))); };
      img.src = url;
    });
  }

  function send(file) {
    var ready = file.size > cfg.maxUpload * 0.95 ? shrink(file) : Promise.resolve(file);
    return ready.then(function (blob) {
      if (blob.size > cfg.maxUpload) throw new Error(t("stillLarge", "still larger than the server accepts after shrinking"));
      var fd = new FormData();
      fd.append("action", "menudash_photo");
      fd.append("_ajax_nonce", cfg.nonce);
      fd.append("name", file.name);
      fd.append("photo", blob, file.name);
      return fetch(cfg.ajax, { method: "POST", body: fd, credentials: "same-origin" });
    }).then(function (res) {
      return res.json().catch(function () { throw new Error(t("serverSaid", "the server answered %s", res.status)); });
    }).then(function (json) {
      if (!json.success) throw new Error((json.data && json.data.error) || t("uploadFail", "failed"));
      return json.data;
    });
  }

  function upload(files) {
    files = Array.prototype.filter.call(files, function (f) { return /^image\//.test(f.type) || /\.(png|jpe?g|webp)$/i.test(f.name); });
    if (!files.length || busy) return;
    busy = true;
    box.hidden = false;
    log.innerHTML = "";
    var done = 0, failed = 0, nomatch = 0;
    var chain = Promise.resolve();
    files.forEach(function (file) {
      chain = chain.then(function () {
        status.textContent = t("uploading", "Uploading %1$s of %2$s: %3$s", done + 1, files.length, file.name);
        return send(file).then(function (data) {
          if (data.dish) {
            line("✓ " + file.name + " → " + data.dish, "ok");
          } else {
            nomatch++;
            line("• " + file.name + " — " + t("noMatch", "uploaded, but matches no dish"), "warn");
          }
        }, function (err) {
          failed++;
          line("✗ " + file.name + " — " + err.message, "err");
        }).then(function () {
          done++;
          bar.style.width = (100 * done / files.length) + "%";
        });
      });
    });
    chain.then(function () {
      busy = false;
      status.textContent = t("done", "Done: %s uploaded", done - failed) +
        (nomatch ? t("notMatched", ", %s not matched to a dish", nomatch) : "") +
        (failed ? t("failed", ", %s failed", failed) : "") + ".";
      var again = document.createElement("button");
      again.type = "button";
      again.className = "button button-primary";
      again.textContent = t("showCheck", "Show the updated check");
      again.addEventListener("click", function () { location.reload(); });
      status.appendChild(document.createTextNode(" "));
      status.appendChild(again);
      input.value = "";
    });
  }

  input.addEventListener("change", function () { upload(input.files); });
  ["dragenter", "dragover"].forEach(function (t) {
    drop.addEventListener(t, function (e) { e.preventDefault(); drop.classList.add("over"); });
  });
  ["dragleave", "drop"].forEach(function (t) {
    drop.addEventListener(t, function () { drop.classList.remove("over"); });
  });
  drop.addEventListener("drop", function (e) {
    e.preventDefault();
    upload(e.dataTransfer.files);
  });

  document.addEventListener("click", function (e) {
    var btn = e.target.closest && e.target.closest(".mdash-del");
    if (!btn || !confirm(t("confirmDel", "Delete this photo?"))) return;
    var fd = new FormData();
    fd.append("action", "menudash_photo_delete");
    fd.append("_ajax_nonce", cfg.nonce);
    fd.append("id", btn.getAttribute("data-id"));
    btn.disabled = true;
    fetch(cfg.ajax, { method: "POST", body: fd, credentials: "same-origin" })
      .then(function (r) { return r.json(); })
      .then(function (json) {
        if (json.success) btn.closest("li").remove();
        else { btn.disabled = false; alert((json.data && json.data.error) || t("deleteFail", "Could not delete.")); }
      });
  });
})();

/* Colours: the preview and the hex code follow the pickers before saving. */
(function () {
  "use strict";
  var preview = document.querySelector(".mdash-colors-preview");
  if (!preview) return;
  function lum(hex) {
    return [1, 3, 5].map(function (i) {
      var c = parseInt(hex.substr(i, 2), 16) / 255;
      return c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
    }).reduce(function (s, c, i) { return s + c * [0.2126, 0.7152, 0.0722][i]; }, 0);
  }
  function contrast(a, b) { var x = lum(a), y = lum(b); return (Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05); }
  /* The theme's colours or my own: the preview shows the chosen ones, and the pickers are
     dimmed while the theme's are in use (a change in them switches to "own"). */
  var themeRadio = document.querySelector('input[name="colors_mode"][value="theme"]');
  var ownRadio = document.querySelector('input[name="colors_mode"][value="own"]');
  var pick = document.querySelector(".mdash-colors-pick");
  function paint(bg, accent) {
    preview.style.setProperty("--p-bg", bg);
    preview.style.setProperty("--p-accent", accent);
    preview.style.setProperty("--p-on", contrast(accent, "#FAF8F1") >= contrast(accent, "#2B1D1A") ? "#FAF8F1" : "#2B1D1A");
  }
  function own() {
    var v = {};
    Array.prototype.forEach.call(document.querySelectorAll("[data-color]"), function (i) { v[i.getAttribute("data-color")] = i.value.toUpperCase(); });
    return v;
  }
  function mode() {
    if (!themeRadio) return;
    var theme = themeRadio.checked;
    if (pick) pick.toggleAttribute("data-off", theme);
    if (theme) paint(themeRadio.getAttribute("data-theme-bg"), themeRadio.getAttribute("data-theme-accent"));
    else { var v = own(); paint(v.bg, v.accent); }
  }
  if (themeRadio) {
    themeRadio.addEventListener("change", mode);
    ownRadio.addEventListener("change", mode);
    mode();
  }
  Array.prototype.forEach.call(document.querySelectorAll("[data-color]"), function (input) {
    input.addEventListener("input", function () {
      if (ownRadio && !ownRadio.checked) { ownRadio.checked = true; mode(); }
      var v = input.value.toUpperCase(), key = input.getAttribute("data-color");
      var label = input.closest(".mdash-color");
      label.querySelector(".mdash-color-hex").textContent = v;
      var def = label.querySelector("small");
      if (def) def.hidden = true;
      preview.style.setProperty(key === "bg" ? "--p-bg" : "--p-accent", v);
      if (key === "accent") preview.style.setProperty("--p-on", contrast(v, "#FAF8F1") >= contrast(v, "#2B1D1A") ? "#FAF8F1" : "#2B1D1A");
    });
  });
})();

/* Meat and fish origin: "+ Add product" copies the empty row from the <template>, Remove
   takes a row off (saved when the form is saved), "Other" shows the three name fields. */
(function () {
  "use strict";
  var add = document.getElementById("mdash-origin-add");
  var tpl = document.getElementById("mdash-origin-new");
  if (!add || !tpl) return;
  var body = document.querySelector(".mdash-origin-admin tbody");
  var max = parseInt(add.getAttribute("data-max"), 10) || 15;
  var next = body.rows.length;
  function update() { add.hidden = body.rows.length >= max; }
  add.addEventListener("click", function () {
    body.insertAdjacentHTML("beforeend", tpl.innerHTML.replace(/__i__/g, String(next++)));
    body.rows[body.rows.length - 1].querySelector("select").focus();
    update();
  });
  body.addEventListener("click", function (e) {
    var rm = e.target.closest && e.target.closest(".mdash-origin-remove");
    if (!rm) return;
    rm.closest("tr").remove();
    update();
  });
  body.addEventListener("change", function (e) {
    if (!e.target.classList.contains("mdash-origin-product")) return;
    var own = e.target.closest("td").querySelector(".mdash-origin-own");
    own.hidden = e.target.value !== "own";
    if (!own.hidden) own.querySelector("input").focus();
  });
})();
