/* Recommended dishes in groups, shown as tabs (includes/picks.php prints the buttons, with
   .has-tabs): one group at a time. Without this script the buttons stay hidden and every
   group shows under its heading, so nothing is lost. */
(function () {
  "use strict";
  function show(box, i, focus) {
    var tabs = box.querySelectorAll(".mdash-picks-tab"), groups = box.querySelectorAll(".mdash-picks-group");
    Array.prototype.forEach.call(tabs, function (b, j) {
      b.setAttribute("aria-selected", i === j ? "true" : "false");
      b.tabIndex = i === j ? 0 : -1;
      if (groups[j]) groups[j].classList.toggle("is-active", i === j);
    });
    if (focus && tabs[i]) tabs[i].focus();
  }
  /* Tabs that slide (.has-slide): the strip slides to the group, and the tab whose group
     fills the left half of the strip is the lit one. */
  function slide(box, i) {
    var strip = box.querySelector(".mdash-picks-strip"), g = box.querySelectorAll(".mdash-picks-group")[i];
    if (!strip || !g) return;
    var smooth = !(window.matchMedia && matchMedia("(prefers-reduced-motion: reduce)").matches);
    strip.scrollTo({ left: g.getBoundingClientRect().left - strip.getBoundingClientRect().left + strip.scrollLeft, behavior: smooth ? "smooth" : "auto" });
  }
  function wire(box) {
    var tabs = Array.prototype.slice.call(box.querySelectorAll(".mdash-picks-tab"));
    var sliding = box.classList.contains("has-slide"), strip = box.querySelector(".mdash-picks-strip");
    var pick = function (i, focus) {
      show(box, i, focus);
      if (sliding) slide(box, i);
    };
    tabs.forEach(function (b, i) {
      b.addEventListener("click", function () { pick(i); });
      b.addEventListener("keydown", function (e) {
        var k = e.key === "ArrowRight" ? 1 : e.key === "ArrowLeft" ? -1 : 0;
        if (k) { e.preventDefault(); pick((i + k + tabs.length) % tabs.length, true); }
      });
    });
    if (sliding && strip) {
      var ticking = false, groups = box.querySelectorAll(".mdash-picks-group");
      strip.addEventListener("scroll", function () {
        if (ticking) return;
        ticking = true;
        requestAnimationFrame(function () {
          ticking = false;
          var r = strip.getBoundingClientRect(), mid = r.left + r.width / 2, k = 0;
          Array.prototype.forEach.call(groups, function (g, i) { if (g.getBoundingClientRect().left <= mid) k = i; });
          if (tabs[k] && tabs[k].getAttribute("aria-selected") !== "true") show(box, k);
        });
      }, { passive: true });
    }
    box.classList.add("js-tabs");
  }
  function all() { Array.prototype.forEach.call(document.querySelectorAll(".menudash-picks.has-tabs"), wire); }
  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", all);
  else all();
})();
