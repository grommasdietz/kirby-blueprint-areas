<template>
  <k-panel-inside
    :key="currentAreaId"
    :data-id="currentAreaId"
    :data-locked="isLocked"
    :data-template="currentAreaId"
    class="k-areas-view"
  >
    <k-header>
      <template #default>
        {{ area.title || area.id }}
      </template>
      <template #buttons>
        <k-view-buttons v-if="viewButtons.length" :buttons="viewButtons" />
        <k-form-controls
          :has-diff="hasDiff"
          :is-locked="isLocked"
          :is-processing="isProcessing"
          :editor="changesEditor"
          :modified="changesModified"
          @discard="onDiscard"
          @submit="onSubmit"
        />
      </template>
    </k-header>

    <k-model-tabs
      v-if="tabs.length > 1"
      :diff="badgeDiff"
      :tab="tab"
      :tabs="tabsForPanel"
    />

    <template v-if="tabs.length && !isBlueprintEmpty">
      <k-sections
        v-for="tabItem in tabs"
        :key="tabName(tabItem)"
        v-show="tabName(tabItem) === tab"
        :tab="tabItem"
        :parent="sectionsParent"
        :content="content"
        :lock="contentLock"
        :blueprint="currentAreaId"
        :empty="emptyMessage"
        @input="onInput"
        @submit="onSubmit"
      />
    </template>
    <k-section v-else>
      <k-box align="start" theme="info" :text="emptyMessage" :html="true" />
    </k-section>
  </k-panel-inside>
</template>

<script>
import {
  refreshBadges,
  setupViewBadgeSync,
  updateMenuBadgeLocal,
} from "../utils/badges.js";

function friendlyError(err) {
  return err?.message || err?.error || err?.details || "Something went wrong";
}

function normalizeList(value) {
  if (Array.isArray(value)) return value;
  if (value && typeof value === "object") return Object.values(value);
  return [];
}

function normalizeTabForBadges(tab) {
  const cloned = JSON.parse(JSON.stringify(tab || {}));

  const tabsColumns = normalizeList(cloned?.columns);
  for (const column of tabsColumns) {
    const sections = normalizeList(column?.sections);
    for (const section of sections) {
      if (!section || typeof section !== "object") continue;
      if (section.type === "gd-fields") {
        section.type = "fields";
      } else if (section.type === "gd-pages") {
        section.type = "pages";
      }
    }
  }

  return cloned;
}

function applyModelPath(layout, modelPath) {
  const cloned = JSON.parse(JSON.stringify(layout || { tabs: [] }));
  if (!modelPath) return cloned;

  const applySections = (sections) => {
    const list = normalizeList(sections);
    for (const section of list) {
      if (section && typeof section === "object") {
        section.gdModelPath = modelPath;
        if (section.type === "fields") {
          section.type = "gd-fields";
        } else if (section.type === "pages") {
          section.type = "gd-pages";
        }
      }
    }
  };

  const tabs = normalizeList(cloned?.tabs);
  for (const tab of tabs) {
    const columns = normalizeList(tab?.columns);
    for (const column of columns) {
      applySections(column?.sections);
    }

    applySections(tab?.sections);
  }

  return cloned;
}

function buildDiff(latest, current) {
  const diff = {};
  const safeLatest = latest && typeof latest === "object" ? latest : {};
  const safeCurrent = current && typeof current === "object" ? current : {};

  for (const [key, value] of Object.entries(safeCurrent)) {
    if (!Object.prototype.hasOwnProperty.call(safeLatest, key)) {
      diff[key] = true;
      continue;
    }
    const changed = JSON.stringify(value);
    const original = JSON.stringify(safeLatest[key]);
    if (changed !== original) {
      diff[key] = true;
    }
  }

  for (const key of Object.keys(safeLatest)) {
    if (!Object.prototype.hasOwnProperty.call(safeCurrent, key)) {
      diff[key] = true;
    }
  }

  return diff;
}

export default {
  props: {
    area: Object,
    lock: [Boolean, Object],
  },
  data() {
    const layout = this.area?.layout || { tabs: [] };
    const tabs = normalizeList(layout?.tabs);
    const firstTab = tabs?.[0]?.name || tabs?.[0]?.id || "main";
    const queryTab = this.$panel?.view?.query?.tab;
    const resolvedTab =
      tabs?.find((tab) => (tab?.name || tab?.id || "main") === queryTab) !==
      undefined
        ? queryTab
        : firstTab;

    return {
      currentAreaId: this.area?.id || null,
      currentMenuId: this.area?.meta?.menuId || null,
      tab: resolvedTab,
      layout: applyModelPath(layout, this.area?.meta?.modelPath || null),
      buttons: this.area?.buttons || [],
    };
  },
  computed: {
    tabs() {
      return normalizeList(this.layout?.tabs);
    },
    tabsForPanel() {
      const basePath = this.$panel?.view?.path
        ? "/" + String(this.$panel.view.path).replace(/^\/+/, "")
        : null;
      const currentQuery = this.$panel?.view?.query || {};
      return this.tabs.map((tab) => {
        const normalizedTab = normalizeTabForBadges(tab);
        const name = this.tabName(normalizedTab);
        let link = null;
        if (basePath !== null) {
          const params = new URLSearchParams();
          for (const [key, value] of Object.entries({
            ...currentQuery,
            tab: name,
          })) {
            if (value !== null && value !== undefined) {
              params.set(key, value);
            }
          }
          link = `${basePath}?${params.toString()}`;
        }
        return {
          ...normalizedTab,
          name,
          label: normalizedTab.label || name,
          link,
        };
      });
    },
    activeTab() {
      return (
        this.tabs.find((t) => this.tabName(t) === this.tab) ||
        this.tabs[0] ||
        null
      );
    },
    content() {
      const versions = this.$panel?.view?.props?.versions;
      if (versions?.changes) {
        return versions.changes;
      }
      return this.area?.values || {};
    },
    rawDiff() {
      const versions = this.$panel?.view?.props?.versions;
      const latest = versions?.latest || this.area?.baseline || {};
      const current = versions?.changes || this.area?.values || {};
      return buildDiff(latest, current);
    },
    badgeDiff() {
      const diff = this.rawDiff || {};
      const props = this.area?.fieldProps || {};
      const syncMap = this.area?.fieldSync || {};
      const exclude = new Set();

      for (const [name, field] of Object.entries(props)) {
        const sync = field?.sync;
        const syncKey =
          typeof sync === "string" && sync !== "" ? sync.toLowerCase() : null;
        if (syncKey && diff[syncKey] !== undefined) {
          exclude.add(name);
        }
      }

      for (const [name, sync] of Object.entries(syncMap)) {
        const syncKey =
          typeof sync === "string" && sync !== "" ? sync.toLowerCase() : null;
        if (syncKey && diff[syncKey] !== undefined) {
          exclude.add(name);
        }
      }

      if (exclude.size === 0) return diff;

      const filtered = {};
      for (const [key, value] of Object.entries(diff)) {
        if (!exclude.has(key)) {
          filtered[key] = value;
        }
      }
      return filtered;
    },
    hasDiff() {
      return Object.keys(this.rawDiff || {}).length > 0;
    },
    isProcessing() {
      return this.localProcessing;
    },
    isLocked() {
      return this.contentLock?.isLocked === true;
    },
    contentLock() {
      if (this.lock && typeof this.lock === "object") {
        return this.lock;
      }
      return false;
    },
    changesEditor() {
      return (
        this.contentLock?.user?.email || this.area?.meta?.changesBy || null
      );
    },
    changesModified() {
      return (
        this.contentLock?.modified || this.area?.meta?.changesModified || null
      );
    },
    isBlueprintEmpty() {
      return this.area?.meta?.isEmpty === true;
    },
    sectionsParent() {
      if (!this.currentAreaId) return null;
      return `grommasdietz/blueprint-areas/blueprints/${this.currentAreaId}`;
    },
    menuId() {
      return (
        this.currentMenuId || this.area?.meta?.menuId || this.currentAreaId
      );
    },
    menuBadgeCount() {
      return this.area?.meta?.menuBadgeCount === true;
    },
    viewButtons() {
      return this.buttons || [];
    },
    emptyMessage() {
      const path = this.area?.meta?.blueprintPath;
      if (path) {
        return `This area has no fields yet. You can define the setup in <strong>${path}</strong>`;
      }
      return "This area has no fields yet.";
    },
  },
  watch: {
    area: {
      handler(next) {
        this.applyViewData(next);
      },
      immediate: true,
    },
  },
  created() {
    this.setupBadgeRefresh();
    this.onSaveShortcut = (event) => {
      if (!this.hasDiff || this.isProcessing || this.isLocked) return;
      event?.preventDefault?.();
      this.onSubmit();
    };
    this.$panel.events.on("keydown.cmd.s", this.onSaveShortcut);
    this.onContentSave = ({ api, language }) => {
      if (
        api === this.$panel.view.props.api &&
        language === this.$panel.language.code
      ) {
        this.syncMenuBadges();
      }
    };
    this.$panel.events.on("content.save", this.onContentSave);
    this.$panel.events.on("content.publish", this.onContentSave);
    this.$panel.events.on("content.discard", this.onContentSave);
    this.syncMenuBadges();
  },
  beforeDestroy() {
    if (this.onSaveShortcut) {
      this.$panel.events.off("keydown.cmd.s", this.onSaveShortcut);
    }
    if (this.onContentSave) {
      this.$panel.events.off("content.save", this.onContentSave);
      this.$panel.events.off("content.publish", this.onContentSave);
      this.$panel.events.off("content.discard", this.onContentSave);
    }
    if (this.onWindowFocus) {
      window.removeEventListener("focus", this.onWindowFocus);
    }
    if (this.onVisibilityChange) {
      document.removeEventListener("visibilitychange", this.onVisibilityChange);
    }
  },
  methods: {
    cloneValues(values) {
      if (this.$helper?.object?.clone) {
        return this.$helper.object.clone(values);
      }
      return JSON.parse(JSON.stringify(values || {}));
    },
    areaFieldKeys() {
      const props = this.area?.fieldProps;
      if (!props || typeof props !== "object") return [];
      return Object.keys(props);
    },
    filterAreaValues(values) {
      const keys = this.areaFieldKeys();
      if (keys.length === 0) return {};
      const allowed = new Set(keys.map((key) => String(key).toLowerCase()));
      const incoming = values && typeof values === "object" ? values : {};
      const filtered = {};
      for (const [key, value] of Object.entries(incoming)) {
        const normalized = String(key).toLowerCase();
        if (allowed.has(normalized)) {
          filtered[normalized] = value;
        }
      }
      return filtered;
    },
    tabName(tab) {
      return tab?.name || tab?.id || "main";
    },
    setupBadgeRefresh() {
      setupViewBadgeSync(this);
    },
    areaDiffCount() {
      const diff = this.badgeDiff || {};
      return Object.keys(diff).length;
    },
    async syncMenuBadges() {
      await refreshBadges(this.$panel);
    },
    updateMenuBadgeLocal() {
      updateMenuBadgeLocal(
        this.$panel,
        this.menuId,
        this.areaDiffCount(),
        this.menuBadgeCount,
      );
    },
    applyViewData(data) {
      if (!data) return;
      const layout = data.layout || { tabs: [] };
      const tabs = normalizeList(layout?.tabs);
      const buttons = this.cloneValues(data.buttons || []);
      const modelPath = data?.meta?.modelPath || null;
      const queryTab = this.$panel?.view?.query?.tab;
      const nextTab =
        tabs?.find((tab) => this.tabName(tab) === queryTab)?.name ||
        tabs?.find((tab) => this.tabName(tab) === queryTab)?.id ||
        tabs?.find((tab) => this.tabName(tab) === this.tab)?.name ||
        tabs?.find((tab) => this.tabName(tab) === this.tab)?.id ||
        tabs?.[0]?.name ||
        tabs?.[0]?.id ||
        "main";

      this.currentAreaId = data.id || this.currentAreaId;
      this.currentMenuId = data.meta?.menuId || this.currentMenuId;
      this.layout = applyModelPath(layout, modelPath);
      this.buttons = buttons;
      this.tab = nextTab;
      this.updateMenuBadgeLocal();
    },
    onInput(values) {
      if (this.isLocked) return;
      const filtered = this.filterAreaValues(values || {});
      try {
        this.$panel.content.updateLazy(filtered);
      } catch (err) {
        this.$panel.notification.error(friendlyError(err));
      }
      this.updateMenuBadgeLocal();
    },
    async onDiscard() {
      const id = this.currentAreaId;
      if (!id || this.isProcessing || this.isLocked) return;

      try {
        await this.$panel.content.discard();
        this.$panel.dialog.close();
        await this.$panel.view.refresh();
        this.updateMenuBadgeLocal();
        this.syncMenuBadges();
      } catch (err) {
        this.$panel.notification.error(friendlyError(err));
      }
    },
    async onSubmit() {
      const id = this.currentAreaId;
      if (!id || this.isProcessing || this.isLocked) return;

      try {
        const filtered = this.filterAreaValues(this.content);
        await this.$panel.content.publish(filtered);
        this.$panel.notification.success();
        await this.$panel.view.refresh();
        this.updateMenuBadgeLocal();
        this.syncMenuBadges();
      } catch (err) {
        this.$panel.notification.error(friendlyError(err));
      }
    },
  },
};
</script>
