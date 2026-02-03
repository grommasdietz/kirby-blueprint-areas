function resolvePayload(menu, data) {
  if (data) return data;
  return menu?.__gdAreasBadgeCache || null;
}

function buildBadgeMap(areas) {
  const map = {};
  const ids = new Set();

  for (const area of areas) {
    if (!area?.id) continue;
    ids.add(area.id);
    map[area.id] = Number(area.count || 0);
  }

  return { map, ids };
}

export function applyMenuBadges(menu, data, options = {}) {
  const payload = resolvePayload(menu, data);
  const areas = Array.isArray(payload?.areas) ? payload.areas : [];
  if (!menu?.entries || areas.length === 0) return menu?.entries ?? null;

  const badgeCount =
    payload?.menuBadgeCount !== undefined
      ? payload.menuBadgeCount === true
      : options.fallbackBadgeCount === true;

  const { map, ids } = buildBadgeMap(areas);

  menu.entries = menu.entries.map((entry) => {
    const id = entry?.id || entry?.link;
    if (!id || !ids.has(id)) {
      return entry;
    }

    const count = map[id] || 0;
    if (count > 0) {
      return {
        ...entry,
        badge: {
          text: badgeCount ? String(count) : "",
          theme: badgeCount ? "notice" : "orange",
        },
      };
    }

    const rest = { ...entry };
    delete rest.badge;
    return rest;
  });

  return menu.entries;
}

export function setupMenuBadges(panel) {
  const menu = panel?.menu;
  if (!menu) return;

  if (panel.__gdAreasMenuRef !== menu) {
    panel.__gdAreasMenuRef = menu;
    menu.__gdAreasWrapped = false;
  }

  if (menu.__gdAreasWrapped) return;

  // Kirby Panel internal: menu.set/view.set are not public APIs.
  menu.__gdAreasWrapped = true;
  const originalSet = menu.set.bind(menu);

  menu.set = (entries) => {
    const state = originalSet(entries);
    const cache = panel.__gdAreasBadgeCache || menu.__gdAreasBadgeCache;
    if (cache) {
      applyMenuBadges(menu, cache);
    }
    return state;
  };

  const cache = panel.__gdAreasBadgeCache || menu.__gdAreasBadgeCache;
  if (cache) {
    applyMenuBadges(menu, cache);
  }

  if (!panel.__gdAreasViewWrapped && panel.view?.set) {
    panel.__gdAreasViewWrapped = true;
    const originalViewSet = panel.view.set.bind(panel.view);
    panel.view.set = (payload) => {
      const state = originalViewSet(payload);
      const nextMenu = panel.menu;
      const nextCache =
        panel.__gdAreasBadgeCache || nextMenu?.__gdAreasBadgeCache;
      if (nextMenu && nextCache) {
        applyMenuBadges(nextMenu, nextCache);
      }
      return state;
    };
  }
}

export async function refreshBadges(panel) {
  setupMenuBadges(panel);
  try {
    const data = await panel.api.get(
      "grommasdietz/kirby-blueprint-areas/changes",
    );
    const menu = panel?.menu;
    if (menu) {
      menu.__gdAreasBadgeCache = data;
    }
    panel.__gdAreasBadgeCache = data;
    applyMenuBadges(menu, data);
  } catch {
    // Keep silent; menu badges are non-critical.
  }
}

export function setupViewBadgeSync(viewInstance) {
  if (typeof window === "undefined") return;
  if (viewInstance.onWindowFocus || viewInstance.onVisibilityChange) return;

  viewInstance.onWindowFocus = () => {
    refreshBadges(viewInstance?.$panel);
  };
  viewInstance.onVisibilityChange = () => {
    if (document.visibilityState === "visible") {
      refreshBadges(viewInstance?.$panel);
    }
  };

  window.addEventListener("focus", viewInstance.onWindowFocus);
  document.addEventListener(
    "visibilitychange",
    viewInstance.onVisibilityChange,
  );
}

export function updateMenuBadgeLocal(panel, menuId, diffCount, badgeCount) {
  const menu = panel?.menu;
  if (!menuId || !menu?.entries) return;

  const entries = menu.entries || [];
  const index = entries.findIndex(
    (entry) => (entry?.id || entry?.link) === menuId,
  );
  if (index < 0) return;

  const entry = entries[index];
  const nextText = badgeCount ? String(diffCount) : "";
  const nextTheme = badgeCount ? "notice" : "orange";

  if (diffCount > 0) {
    if (entry?.badge?.text === nextText && entry?.badge?.theme === nextTheme) {
      // No visual change required.
    } else {
      menu.entries.splice(index, 1, {
        ...entry,
        badge: {
          text: nextText,
          theme: nextTheme,
        },
      });
    }
  } else if (entry?.badge) {
    const rest = { ...entry };
    delete rest.badge;
    menu.entries.splice(index, 1, rest);
  }

  const cached = panel?.__gdAreasBadgeCache || menu.__gdAreasBadgeCache;
  const cachedBadgeCount =
    badgeCount === undefined ? cached?.menuBadgeCount : badgeCount;
  const nextAreas = Array.isArray(cached?.areas) ? [...cached.areas] : [];
  const areaIndex = nextAreas.findIndex((area) => area?.id === menuId);
  const nextEntry = { id: menuId, count: diffCount };
  if (areaIndex >= 0) {
    nextAreas[areaIndex] = { ...nextAreas[areaIndex], ...nextEntry };
  } else {
    nextAreas.push(nextEntry);
  }
  const nextCache = {
    areas: nextAreas,
    menuBadgeCount: cachedBadgeCount,
  };
  menu.__gdAreasBadgeCache = nextCache;
  if (panel) {
    panel.__gdAreasBadgeCache = nextCache;
  }
}
