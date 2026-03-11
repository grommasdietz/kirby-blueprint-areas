/**
 * Source file for kirbyup. Bundles Vue SFCs into the shipped index.js.
 */

import AreasView from "./components/AreasView.vue";
import GDFieldsSection from "./components/sections/GDFieldsSection.js";
import GDPagesSection from "./components/sections/GDPagesSection.js";
import { refreshBadges, setupMenuBadges } from "./utils/badges.js";

const PLUGIN_NAME = "grommasdietz/blueprint-areas";
const AREAS_API_PREFIX = `${PLUGIN_NAME}/blueprints/`;

function isAreasApi(api) {
  return typeof api === "string" && api.startsWith(AREAS_API_PREFIX);
}

function areasApiEndpoint(api, method) {
  switch (method) {
    case "save":
      return `${api}/save`;
    case "publish":
      return `${api}/publish`;
    case "discard":
      return `${api}/discard`;
    default:
      return `${api}/${method}`;
  }
}

(function () {
  if (typeof window === "undefined" || !window.panel) return;

  window.panel.plugin(PLUGIN_NAME, {
    components: {
      "k-areas-view": AreasView,
      "k-gd-fields-section": GDFieldsSection,
      "k-gd-pages-section": GDPagesSection,
    },
    created(app) {
      const panel = app?.$panel;
      if (!panel) return;

      // Kirby Panel internal: redirect content requests for area APIs away
      // from core /changes/* routes (see Kirby panel/content.js).
      if (!panel.__gdAreasContentPatched && panel.content?.request) {
        panel.__gdAreasContentPatched = true;
        const originalRequest = panel.content.request.bind(panel.content);

        panel.content.request = async (
          method = "save",
          values = {},
          env = {},
        ) => {
          const { api, language } = panel.content.env(env);

          if (isAreasApi(api)) {
            const options = {
              headers: {
                "x-language": language,
              },
            };

            if (method === "save") {
              options.signal = panel.content.saveAbortController?.signal;
              options.silent = true;
            }

            return panel.api.post(
              areasApiEndpoint(api, method),
              values,
              options,
            );
          }

          return originalRequest(method, values, env);
        };
      }

      setupMenuBadges(panel);

      const onContentChange = () => refreshBadges(panel);
      panel.events.on("content.save", onContentChange);
      panel.events.on("content.publish", onContentChange);
      panel.events.on("content.discard", onContentChange);

      refreshBadges(panel);
    },
  });
})();
