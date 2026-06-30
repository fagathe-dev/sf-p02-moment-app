const ROUTES = {
  API: {
    VAULT: {
      CHECK: `/api/vault/check`,
      VERIFY: `/api/vault/verify`,
      ENTRIES: `/app/vault/journal/api/entries`,
      ENTRY_DATA: `/app/vault/journal/api/entry/{id}`,
    },
  },
} as const;

export { ROUTES };