export default {
  extends: "k-pages-section",
  props: {
    gdModelPath: String,
  },
  methods: {
    onAdd() {
      if (this.canAdd) {
        const view = this.gdModelPath ?? this.parent;
        const parent = this.options?.link ?? view;
        this.$dialog("pages/create", {
          query: {
            parent,
            view,
            section: this.name,
          },
        });
      }
    },
  },
};
