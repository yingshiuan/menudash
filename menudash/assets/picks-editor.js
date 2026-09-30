/* The Recommended dishes block in the editor (includes/picks.php): which dishes and how they
   look in the sidebar, and a preview drawn by the server. The block saves only its options;
   a chosen dish is kept as its key and name, so it is found again after a new menu. */
(function (blocks, element, blockEditor, components, ServerSideRender) {
  "use strict";
  var el = element.createElement;
  var useState = element.useState;
  var MENU = (window.menudashPicks && window.menudashPicks.dishes) || [];
  var byKey = {};
  MENU.forEach(function (d) { byKey[d.key] = d; });

  function label(d) { return (d.no ? d.no + " · " : "") + d.name; }
  function fold(s) { return String(s || "").toLowerCase().normalize("NFD").replace(/[̀-ͯ]/g, ""); }

  /* The live dish for a saved choice: by key while the name still fits, else by name. */
  function find(ref) {
    var d = byKey[ref.key];
    if (d && (!ref.name || fold(ref.name) === fold(d.name))) return d;
    for (var i = 0; i < MENU.length; i++) if (fold(MENU[i].name) === fold(ref.name)) return MENU[i];
    return d || null;
  }

  function Thumb(props) {
    return props.src
      ? el("img", { src: props.src, alt: "", width: 32, height: 32, style: { width: 32, height: 32, objectFit: "contain", flex: "none" } })
      : el("span", { style: { width: 32, height: 32, flex: "none", borderRadius: "50%", background: "#eee", display: "inline-block" }, title: "No photo yet" });
  }

  function Row(props) {
    return el("div", { style: { display: "flex", alignItems: "center", gap: 8, padding: "4px 0", borderBottom: "1px solid #eee" } },
      el(Thumb, { src: props.dish ? props.dish.photo : "" }),
      el("span", { style: { flex: 1, minWidth: 0, fontSize: 13, color: props.dish ? "inherit" : "#b32d2e" } },
        props.dish ? label(props.dish) : props.missing + " (not on the menu)"),
      props.children);
  }

  function Chosen(props) {
    var list = props.value || [], set = props.onChange;
    var q = useState(""), query = q[0], setQuery = q[1];
    var chosenKeys = {};
    list.forEach(function (r) { var d = find(r); if (d) chosenKeys[d.key] = true; });
    var move = function (i, by) {
      var next = list.slice(), t = next[i];
      next[i] = next[i + by]; next[i + by] = t; set(next);
    };
    var f = fold(query.trim());
    var hits = !f ? [] : MENU.filter(function (d) {
      return !chosenKeys[d.key] && (fold(d.all + " " + d.sec).indexOf(f) >= 0 || d.no === query.trim());
    }).slice(0, 30);

    return el("div", null,
      list.length ? el("div", { style: { marginBottom: 12 } },
        list.map(function (r, i) {
          var d = find(r);
          return el(Row, { key: r.key + i, dish: d, missing: r.name || r.key },
            el(components.Button, { icon: "arrow-up-alt2", label: "Move up", size: "small", disabled: i === 0, onClick: function () { move(i, -1); } }),
            el(components.Button, { icon: "arrow-down-alt2", label: "Move down", size: "small", disabled: i === list.length - 1, onClick: function () { move(i, 1); } }),
            el(components.Button, { icon: "no-alt", label: "Remove", size: "small", isDestructive: true, onClick: function () { set(list.filter(function (x, j) { return j !== i; })); } }));
        })) : el("p", { className: "components-base-control__help" }, "No dishes chosen yet. Search the menu below and add them."),
      el(components.SearchControl, { label: "Add a dish", value: query, placeholder: "Number or name", onChange: setQuery, __nextHasNoMarginBottom: true }),
      f && !hits.length ? el("p", { className: "components-base-control__help" }, "No dish found.") : null,
      hits.map(function (d) {
        return el(Row, { key: d.key, dish: d },
          el(components.Button, { variant: "secondary", size: "small", onClick: function () {
            set(list.concat([{ key: d.key, name: d.name }]));
          } }, "Add"));
      }),
      !MENU.length ? el("p", { className: "components-base-control__help" }, "No menu yet: upload one under MenuDash → Menu.") : null
    );
  }

  var TITLES = (window.menudashPicks && window.menudashPicks.titles) || ["Meat & fish", "Vegan & vegetarian"];
  function ref(d) { return { key: d.key, name: d.name }; }
  function recommended(max) { return MENU.filter(function (d) { return d.pick; }).slice(0, max || 12); }
  /* Two groups from a list of dishes: meat and fish, then vegetarian and vegan. */
  function byDiet(dishes) {
    return [
      { title: TITLES[0], dishes: dishes.filter(function (d) { return !d.veg; }).map(ref) },
      { title: TITLES[1], dishes: dishes.filter(function (d) { return d.veg; }).map(ref) }
    ];
  }

  function Groups(props) {
    var groups = props.value || [], set = props.onChange;
    var change = function (i, part) {
      set(groups.map(function (g, j) { return j === i ? Object.assign({}, g, part) : g; }));
    };
    return el("div", null,
      groups.map(function (g, i) {
        return el("div", { key: i, style: { border: "1px solid #ddd", borderRadius: 4, padding: "10px 10px 4px", marginBottom: 12 } },
          el(components.TextControl, { label: "Group " + (i + 1) + ": title", value: g.title || "", onChange: function (v) { change(i, { title: v }); }, __nextHasNoMarginBottom: true }),
          el("div", { style: { height: 8 } }),
          el(Chosen, { value: g.dishes || [], onChange: function (v) { change(i, { dishes: v }); } }),
          el(components.Button, { variant: "link", isDestructive: true, style: { margin: "8px 0" }, onClick: function () { set(groups.filter(function (x, j) { return j !== i; })); } }, "Remove this group"));
      }),
      el(components.Button, { variant: "secondary", onClick: function () { set(groups.concat([{ title: "", dishes: [] }])); } }, "+ Add a group"));
  }

  /* The preview's tabs switch here too (the page does it with assets/picks.js); a click on a
     dish link does nothing in the editor. */
  function switchTab(e) {
    var t = e.target.closest && e.target.closest(".mdash-picks-tab, .mdash-pick a");
    if (!t) return;
    e.preventDefault();
    if (!t.classList.contains("mdash-picks-tab")) return;
    var box = t.closest(".menudash-picks"), tabs = box.querySelectorAll(".mdash-picks-tab"), groups = box.querySelectorAll(".mdash-picks-group");
    Array.prototype.forEach.call(tabs, function (b, j) {
      var on = b === t;
      b.setAttribute("aria-selected", on ? "true" : "false");
      if (groups[j]) groups[j].classList.toggle("is-active", on);
      var strip = box.querySelector(".mdash-picks-strip");
      if (on && strip && groups[j]) strip.scrollTo({ left: groups[j].getBoundingClientRect().left - strip.getBoundingClientRect().left + strip.scrollLeft, behavior: "smooth" });
    });
  }

  blocks.registerBlockType("menudash/picks", {
    edit: function (props) {
      var a = props.attributes, set = props.setAttributes;
      var picks = MENU.filter(function (d) { return d.pick; }).length;
      var groups = a.groups || [];
      var title = function (i, v) {
        var next = [0, 1].map(function (j) { return { title: (groups[j] && groups[j].title) || "", dishes: [] }; });
        next[i].title = v;
        set({ groups: next });
      };
      var dishesPanel = [];
      if (a.source === "chosen" && a.split) {
        dishesPanel.push(el(Groups, { key: "g", value: groups, onChange: function (v) { set({ groups: v }); } }));
      } else if (a.source === "chosen") {
        dishesPanel.push(el(Chosen, { key: "c", value: a.dishes, onChange: function (v) { set({ dishes: v }); } }));
      } else {
        dishesPanel.push(el("p", { key: "h", className: "components-base-control__help" }, "Change them with the Recommended column in the menu spreadsheet. Dishes with a photo come first."));
        if (a.split) {
          dishesPanel.push(el("p", { key: "s", className: "components-base-control__help" }, "Split by the diet marks in the spreadsheet: dishes marked vegetarian or vegan go in the second group."));
          [0, 1].forEach(function (i) {
            dishesPanel.push(el(components.TextControl, { key: "t" + i, label: "Group " + (i + 1) + ": title", value: (groups[i] && groups[i].title) || "", placeholder: TITLES[i], onChange: function (v) { title(i, v); } }));
          });
        }
      }
      return el(element.Fragment, null,
        el(blockEditor.InspectorControls, null,
          el(components.PanelBody, { title: "Dishes" },
            el(components.RadioControl, {
              label: "Show",
              selected: a.source,
              options: [
                { label: "Marked Recommended in the menu (" + picks + ")", value: "pick" },
                { label: "The dishes I choose", value: "chosen" }
              ],
              onChange: function (v) {
                var next = { source: v };
                // Starting to choose: begin with the recommended ones, easier to change than an empty list.
                if (v === "chosen" && !(a.dishes || []).length) next.dishes = recommended(a.max).map(ref);
                if (v === "chosen" && a.split && !groups.some(function (g) { return (g.dishes || []).length; })) {
                  next.groups = byDiet(recommended(a.max)).map(function (g, i) { return { title: (groups[i] && groups[i].title) || g.title, dishes: g.dishes }; });
                }
                set(next);
              }
            }),
            el(components.ToggleControl, {
              label: "Split into groups",
              help: a.source === "chosen" ? "Groups with their own title and dishes, e.g. Meat & fish / Vegan & vegetarian." : "Meat & fish, and vegan & vegetarian.",
              checked: !!a.split,
              onChange: function (v) {
                var next = { split: v };
                if (v && a.source === "chosen" && !groups.some(function (g) { return (g.dishes || []).length; })) {
                  // The chosen dishes, split by diet to begin with.
                  next.groups = byDiet((a.dishes || []).map(find).filter(Boolean));
                }
                set(next);
              }
            }),
            a.split && el(components.SelectControl, {
              label: "Groups are shown as",
              value: a.groupStyle,
              options: [
                { label: "Tabs (one group at a time)", value: "tabs" },
                { label: "Tabs that slide (all groups in one row)", value: "slide" },
                { label: "Headings (all groups)", value: "headings" }
              ],
              help: a.groupStyle === "slide" ? "One row that slides sideways: a tab slides to its group, and swiping moves the tab. Six dishes to a screen on a computer." : undefined,
              onChange: function (v) { set({ groupStyle: v }); }
            }),
            dishesPanel,
            el(components.RangeControl, { label: a.split ? "Show at most (per group)" : "Show at most", value: a.max, min: 1, max: 48, onChange: function (v) { set({ max: v || 12 }); } }),
            el("p", { className: "components-base-control__help" }, "The photos are the dishes' photos under MenuDash → Dish photos. To change a picture, upload a new photo for that dish there.")
          ),
          el(components.PanelBody, { title: "Look", initialOpen: true },
            el(components.SelectControl, {
              label: "Layout",
              value: a.layout,
              options: [{ label: "Grid", value: "grid" }, { label: "One row that slides", value: "row" }],
              onChange: function (v) { set({ layout: v }); }
            }),
            el(components.SelectControl, {
              label: "Language",
              value: a.lang,
              options: [
                { label: "Site language", value: "" },
                { label: "Deutsch", value: "de" },
                { label: "English", value: "en" },
                { label: "中文", value: "zh" }
              ],
              onChange: function (v) { set({ lang: v }); }
            }),
            el(components.ToggleControl, { label: "Dish number", checked: !!a.number, onChange: function (v) { set({ number: v }); } }),
            el(components.ToggleControl, { label: "Vegan / vegetarian mark", help: "After the number, e.g. No. 400 · Vegan.", checked: !!a.diet, onChange: function (v) { set({ diet: v }); } }),
            el(components.ToggleControl, { label: "Second name (Chinese)", checked: !!a.second, onChange: function (v) { set({ second: v }); } }),
            el(components.ToggleControl, { label: "Button to the whole menu", checked: !!a.button, onChange: function (v) { set({ button: v }); } }),
            a.button && el(components.TextControl, {
              label: "Button text",
              value: a.buttonText,
              placeholder: "Empty: \"See the whole menu\" in the language",
              onChange: function (v) { set({ buttonText: v }); }
            })
          )
        ),
        el("div", blockEditor.useBlockProps({ className: "mdash-picks-editor", onClick: switchTab }),
          el(ServerSideRender, { block: "menudash/picks", attributes: a }))
      );
    },
    save: function () { return null; }
  });
})(window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.components, window.wp.serverSideRender);
