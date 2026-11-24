export default function readOnlyModelList(extendsComponent) {
  return {
    extends: extendsComponent,
    computed: {
      buttons() { return []; },
      canAdd() { return false; },
      canDrop() { return false; },
      canSelect() { return false; },
      isSortable() { return false; },
      items() {
        return (this.state.models || []).map(model => ({
          ...model,
          options: false,
          selectable: false,
          sortable: false,
        }));
      },
    },
  };
}
