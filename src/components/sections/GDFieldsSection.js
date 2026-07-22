export default {
  extends: "k-fields-section",
  props: {
    gdModelPath: String,
    gdReadOnly: Boolean,
  },
  methods: {
    async fetch() {
      try {
        const response = await this.load();
        this.fields = response.fields;

        const model = this.gdModelPath ?? this.parent;

        for (const name in this.fields) {
          this.fields[name].disabled =
            this.gdReadOnly === true || this.fields[name].disabled === true;
          this.fields[name].section = this.name;
          this.fields[name].endpoints = {
            field: this.parent + "/fields/" + name,
            section: this.parent + "/sections/" + this.name,
            model,
          };
        }
      } catch (error) {
        this.issue = error;
      } finally {
        this.isLoading = false;
      }
    },
  },
};
