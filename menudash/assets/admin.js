/* MenuDash admin page: upload photos one at a time, so no single request is ever
   bigger than one photo, and shrink a photo in the browser first when it is over the
   server's limit. */
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
          c.toBlob(function (png) { png ? resolve(png) : reject(new Error("could not shrink")); }, "image/png");
        }, "image/webp", 0.92);
      };
      img.onerror = function () { URL.revokeObjectURL(url); reject(new Error("not an image")); };
      img.src = url;
    });
  }

  function send(file) {
    var ready = file.size > cfg.maxUpload * 0.95 ? shrink(file) : Promise.resolve(file);
    return ready.then(function (blob) {
      if (blob.size > cfg.maxUpload) throw new Error("still larger than the server accepts after shrinking");
      var fd = new FormData();
      fd.append("action", "menudash_photo");
      fd.append("_ajax_nonce", cfg.nonce);
      fd.append("name", file.name);
      fd.append("photo", blob, file.name);
      return fetch(cfg.ajax, { method: "POST", body: fd, credentials: "same-origin" });
    }).then(function (res) {
      return res.json().catch(function () { throw new Error("the server answered " + res.status); });
    }).then(function (json) {
      if (!json.success) throw new Error((json.data && json.data.error) || "failed");
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
        status.textContent = "Uploading " + (done + 1) + " of " + files.length + ": " + file.name;
        return send(file).then(function (data) {
          if (data.dish) {
            line("✓ " + file.name + " → " + data.dish, "ok");
          } else {
            nomatch++;
            line("• " + file.name + " — uploaded, but matches no dish", "warn");
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
      status.textContent = "Done: " + (done - failed) + " uploaded" +
        (nomatch ? ", " + nomatch + " not matched to a dish" : "") +
        (failed ? ", " + failed + " failed" : "") + ".";
      var again = document.createElement("button");
      again.type = "button";
      again.className = "button button-primary";
      again.textContent = "Show the updated check";
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
    if (!btn || !confirm("Delete this photo?")) return;
    var fd = new FormData();
    fd.append("action", "menudash_photo_delete");
    fd.append("_ajax_nonce", cfg.nonce);
    fd.append("id", btn.getAttribute("data-id"));
    btn.disabled = true;
    fetch(cfg.ajax, { method: "POST", body: fd, credentials: "same-origin" })
      .then(function (r) { return r.json(); })
      .then(function (json) {
        if (json.success) btn.closest("li").remove();
        else { btn.disabled = false; alert((json.data && json.data.error) || "Could not delete."); }
      });
  });
})();
