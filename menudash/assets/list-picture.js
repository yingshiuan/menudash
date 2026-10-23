/* MenuDash guest list: the list drawn as a picture, like a small flyer, for "Share" (menu.js
   loads this file only when a guest taps it) and for the preview on the Menu tab. Drawn on the
   phone; nothing is sent. Phone sized: a 390-point page at 3×, never shorter than a phone screen.
   No prices: the picture is passed on, and prices change (the list in the page has them).

   mdashListPicture(o) returns a canvas. o:
     rows   [{ n: how many, no: "12", name: "…", subs: ["the German name", …], q: true for a question,
              img: the dish's loaded photo (or null), sq: true for a square photo, false for a cut-out }]
     photos true: a column of photos on the right of the names (a dish without one keeps its place)
     title  "Meine Liste", date "23. Oktober 2026", qword "Frage"
     logo   a loaded Image of this site (or null: the site's name instead), site "Sample Kitchen"
     place  { name, address, phone } for the foot, link "example.com/menu" (printed)
     qr     a made qrcode() object (or null), colors { accent, ink, muted, bg, onAccent }, fonts { sans, display } */
(function () {
  "use strict";

  // Lines that fit a width: by words, and by characters for Chinese (or a very long word).
  function wrap(c, text, width) {
    var out = [], line = "";
    (String(text).match(/[⺀-鿿豈-﫿＀-￯]|[^\s⺀-鿿豈-﫿＀-￯]+\s*|\s+/g) || []).forEach(function (t) {
      if (line && c.measureText(line + t).width > width) { out.push(line.trim()); line = t.replace(/^\s+/, ""); }
      else line += t;
    });
    if (line.trim()) out.push(line.trim());
    return out;
  }

  // A dish photo in a square: a cut-out whole on the page, a square photo filling it, rounded.
  function photo(c, img, x, y, s, sq) {
    var w = img.naturalWidth, h = img.naturalHeight, k = sq ? Math.max(s / w, s / h) : Math.min(s / w, s / h);
    c.save();
    if (sq) {
      c.beginPath();
      if (c.roundRect) c.roundRect(x, y, s, s, 8); else c.rect(x, y, s, s);
      c.clip();
    }
    c.drawImage(img, x + (s - w * k) / 2, y + (s - h * k) / 2, w * k, h * k);
    c.restore();
  }

  window.mdashListPicture = function (o) {
    var W = 390, X = 3, P = 24;
    var col = o.colors, sans = o.fonts.sans, display = o.fonts.display, place = o.place || {}, qr = o.qr, logo = o.logo;

    // One pass to measure (paint false), one to paint; in points, the canvas is scaled.
    function draw(c, paint, H) {
      var y = P;
      function text(str, x, font, color) {
        c.font = font;
        if (paint) { c.fillStyle = color; c.fillText(str, x, y); }
      }
      function rule(h, color) {
        if (paint) { c.fillStyle = color; c.fillRect(P, y, W - 2 * P, h); }
      }
      c.textBaseline = "top";
      c.textAlign = "left";
      if (logo) { // the site's small logo, 40 points high (at most 140 wide)
        var lh = 40, lw = lh * logo.naturalWidth / logo.naturalHeight;
        if (lw > 140) { lh = lh * 140 / lw; lw = 140; }
        if (paint) c.drawImage(logo, P, y, lw, lh);
        y += lh + 18;
      } else {
        text(o.site, P, "800 26px " + display, col.accent); y += 36;
      }
      text(o.title, P, "800 30px " + display, col.ink); y += 34;
      text(o.date, P, "500 15px " + sans, col.muted); y += 26;
      rule(1.5, col.ink); y += 1.5;
      var F = o.photos ? 56 : 0, fx = W - P - F; // the photos' column on the right, 56 points square
      var nameX = P + 66, nameW = W - P - nameX - (F ? F + 12 : 0);
      o.rows.forEach(function (r) {
        y += 13;
        var top = y;
        if (F && r.img && paint) photo(c, r.img, fx, top - 2, F, r.sq);
        text(r.n + "×", P, "800 17px " + sans, col.accent);
        text(r.no, P + 30, "700 17px " + sans, col.ink);
        c.font = "700 17px " + sans;
        wrap(c, r.name, nameW).forEach(function (l) { text(l, nameX, "700 17px " + sans, col.ink); y += 22; });
        r.subs.forEach(function (sub) {
          c.font = "400 14px " + sans;
          wrap(c, sub, nameW).forEach(function (l) { y += 1; text(l, nameX, "400 14px " + sans, col.muted); y += 18; });
        });
        if (r.q) {
          c.font = "700 13px " + sans;
          var qw = c.measureText(o.qword).width + 16;
          y += 5;
          if (paint) {
            c.fillStyle = col.accent;
            c.beginPath();
            if (c.roundRect) c.roundRect(nameX, y, qw, 21, 10.5); else c.rect(nameX, y, qw, 21);
            c.fill();
          }
          y += 4; text(o.qword, nameX + 8, "700 13px " + sans, col.onAccent); y += 17;
        }
        y = Math.max(y, top + (F ? F - 2 : 22)) + 12;
        rule(1, "rgba(43, 29, 26, .16)"); y += 1;
      });
      // The foot: a band in the menu's colour at the bottom of the picture with the restaurant's
      // name, address, phone and the menu's link, and a QR code to the menu.
      var Q = qr ? 92 : 0, tw = W - 2 * P - (qr ? Q + 16 : 0);
      var foot = [];
      c.font = "800 19px " + display;
      wrap(c, place.name || o.site, tw).forEach(function (l) { foot.push([l, "800 19px " + display, 24]); });
      c.font = "400 14px " + sans;
      [place.address, place.phone].forEach(function (x) {
        if (x) wrap(c, x, tw).forEach(function (l) { foot.push([l, "400 14px " + sans, 19]); });
      });
      c.font = "700 14px " + sans;
      wrap(c, o.link, tw).forEach(function (l, i) { foot.push([l, "700 14px " + sans, 19, i ? 0 : 6]); });
      var th = foot.reduce(function (h, f) { return h + f[2] + (f[3] || 0); }, 0);
      var bandH = Math.max(th, Q) + 2 * P;
      y = Math.max(y + 28, (H || 0) - bandH);
      if (paint) { c.fillStyle = col.accent; c.fillRect(0, y, W, bandH); }
      var by = y, fy = y + P + Math.max(0, (Q - th) / 2);
      foot.forEach(function (f) { fy += f[3] || 0; y = fy; text(f[0], P, f[1], col.onAccent); fy += f[2]; });
      if (qr && paint) {
        var n = qr.getModuleCount(), qx = W - P - Q, qy = by + (bandH - Q) / 2, m = Q / (n + 6); // a quiet zone of 3
        c.fillStyle = "#fff";
        c.beginPath();
        if (c.roundRect) c.roundRect(qx, qy, Q, Q, 8); else c.rect(qx, qy, Q, Q);
        c.fill();
        // One pixel per module on a small canvas, enlarged without smoothing: sharp, no hairlines.
        var mini = document.createElement("canvas"), mc = mini.getContext("2d");
        mini.width = mini.height = n;
        mc.fillStyle = col.ink;
        for (var i = 0; i < n; i++) for (var j = 0; j < n; j++) if (qr.isDark(i, j)) mc.fillRect(j, i, 1, 1);
        c.imageSmoothingEnabled = false;
        c.drawImage(mini, qx + 3 * m, qy + 3 * m, n * m, n * m);
        c.imageSmoothingEnabled = true;
      }
      return by + bandH;
    }

    var cv = document.createElement("canvas");
    var c = cv.getContext("2d");
    var H = Math.max(draw(c, false), Math.round(W * 16 / 9)); // at least a phone screen
    var k = Math.min(X, Math.sqrt(16e6 / (W * H))); // phones refuse larger pictures
    cv.width = Math.round(W * k);
    cv.height = Math.round(H * k);
    c = cv.getContext("2d");
    c.scale(k, k);
    c.fillStyle = col.bg;
    c.fillRect(0, 0, W, H);
    draw(c, true, H);
    return cv;
  };
})();
