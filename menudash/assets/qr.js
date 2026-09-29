/* MenuDash QR code: draws the codes on the QR code tab and the print sheet (qrcode.js, in the
   browser), keeps the preview in step with the form, and downloads the code as SVG or PNG. */
(function () {
  "use strict";
  if (typeof qrcode !== "function") return;

  // Error correction M: still reads with a scratch or a coffee stain, and stays simple.
  function matrix(url) {
    var qr = qrcode(0, "M");
    qr.addData(unescape(encodeURIComponent(url)), "Byte"); // UTF-8, for links with ä or 中文
    qr.make();
    return qr;
  }

  // An SVG with one path; a quiet zone of 4 modules around it, as scanners expect.
  function svg(url) {
    var qr = matrix(url), n = qr.getModuleCount(), q = 4, d = "";
    for (var r = 0; r < n; r++) {
      for (var c = 0; c < n; c++) {
        if (qr.isDark(r, c)) d += "M" + (c + q) + " " + (r + q) + "h1v1h-1z";
      }
    }
    var size = n + 2 * q;
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' + size + " " + size + '" shape-rendering="crispEdges">' +
      '<rect width="100%" height="100%" fill="#fff"/><path d="' + d + '" fill="#000"/></svg>';
  }

  function draw(box) {
    var url = box.getAttribute("data-url");
    box.innerHTML = url ? svg(url) : "";
  }
  Array.prototype.forEach.call(document.querySelectorAll(".mdash-qr-code, .mdash-qr-wcode"), draw);

  // A full card (three languages, a message, Wi-Fi) shrinks text and codes step by step until
  // everything fits, at most to 70 %; the main code stays big enough to scan from a table.
  function fit(c) {
    var k = 1;
    c.style.setProperty("--k", k);
    while (c.scrollHeight > c.clientHeight + 1 && k > 0.71) {
      k = Math.round((k - 0.03) * 100) / 100;
      c.style.setProperty("--k", k);
    }
  }
  function fitAll() { Array.prototype.forEach.call(document.querySelectorAll(".mdash-qr-card"), fit); }
  fitAll();
  if (document.fonts && document.fonts.ready) document.fonts.ready.then(fitAll);
  window.addEventListener("load", fitAll);

  // Same as mdash_qr_wifi_code() in PHP.
  function wifi(name, pass) {
    var esc = function (v) { return v.replace(/([\\;,:"])/g, "\\$1"); };
    return pass ? "WIFI:T:WPA;S:" + esc(name) + ";P:" + esc(pass) + ";;" : "WIFI:T:nopass;S:" + esc(name) + ";;";
  }

  var form = document.querySelector(".mdash-qr-form");
  if (!form) return;
  var card = document.querySelector(".mdash-qr-preview .mdash-qr-card");
  var code = card.querySelector(".mdash-qr-code");
  var field = function (k) { return form.querySelector("[name='qr[" + k + "]']"); };
  var part = function (k) { return card.querySelector("[data-qr-show='" + k + "']"); };

  function link() {
    var u = field("url").value.trim();
    if (!u) return field("url").getAttribute("data-default");
    return /^https?:\/\//i.test(u) ? u : "https://" + u;
  }

  function update() {
    var t = field("text").value.trim(), n = field("wifi_name").value.trim(), p = field("wifi_pass").value.trim();
    var m = form.querySelector("[name='qr[note]']").value.split("\n").map(function (l) { return l.trim(); }).filter(Boolean).slice(0, 4).join("\n");
    part("note").textContent = m;
    part("note").hidden = !m;
    part("wifi_name").textContent = n;
    part("wifi_pass").textContent = p;
    part("wifi").hidden = !n;
    part("wifi_pass_line").hidden = !p;
    // The card's words in the ticked languages, menu order, each word once (mdash_qr_word()).
    var langs = Array.prototype.map.call(form.querySelectorAll("[name='qr[langs][]']:checked"), function (b) { return b.value; });
    if (!langs.length) langs = ["de", "en", "zh"];
    var words = JSON.parse(card.getAttribute("data-words") || "{}"), sep = { qr_title: "\n", qr_tip: "\n", wifi: " / ", password: " / " };
    var say = function (k) {
      var seen = [];
      ["de", "en", "zh"].forEach(function (l) { var w = words[k] && words[k][l]; if (langs.indexOf(l) >= 0 && w && seen.indexOf(w) < 0) seen.push(w); });
      return seen.join(sep[k]);
    };
    Array.prototype.forEach.call(card.querySelectorAll("[data-qr-word]"), function (el) { el.textContent = say(el.getAttribute("data-qr-word")); });
    part("text").textContent = t || say("qr_title");
    card.querySelector(".mdash-qr-tip").hidden = !form.querySelector("input[type=checkbox][name='qr[tip]']").checked;
    var wc = card.querySelector(".mdash-qr-wcode"), w = n ? wifi(n, p) : "";
    if (wc.getAttribute("data-url") !== w) { wc.setAttribute("data-url", w); draw(wc); }
    var u = link();
    if (code.getAttribute("data-url") !== u) {
      code.setAttribute("data-url", u);
      code.setAttribute("aria-label", "QR code: " + u);
      part("url").textContent = u.replace(/^https?:\/\/(www\.)?/i, "").replace(/\/$/, "");
      draw(code);
    }
    fit(card);
  }
  form.addEventListener("input", update);

  function save(blob, name) {
    var a = document.createElement("a");
    a.href = URL.createObjectURL(blob);
    a.download = name;
    document.body.appendChild(a);
    a.click();
    setTimeout(function () { URL.revokeObjectURL(a.href); a.remove(); }, 1000);
  }

  Array.prototype.forEach.call(document.querySelectorAll("[data-qr-download]"), function (b) {
    b.addEventListener("click", function () {
      var s = svg(link());
      if (b.getAttribute("data-qr-download") === "svg") {
        save(new Blob([s], { type: "image/svg+xml" }), "menu-qr-code.svg");
        return;
      }
      // PNG: 1200 px, enough for A4 posters.
      var img = new Image(), px = 1200;
      img.onload = function () {
        var c = document.createElement("canvas");
        c.width = c.height = px;
        var g = c.getContext("2d");
        g.imageSmoothingEnabled = false;
        g.drawImage(img, 0, 0, px, px);
        c.toBlob(function (png) { save(png, "menu-qr-code.png"); }, "image/png");
      };
      img.src = "data:image/svg+xml;charset=utf-8," + encodeURIComponent(s);
    });
  });
})();
