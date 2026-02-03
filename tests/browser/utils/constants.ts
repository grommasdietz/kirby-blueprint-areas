/**
 * Shared constants for tests, such as product IDs, timeouts, or URLs.
 * Keeps magic strings/numbers centralized.
 */

// Common timeouts (fallback if not using TIMEOUTS from helpers)
export const DEFAULT_TIMEOUTS = {
  NETWORK: 10000,
  DOM: 5000,
  STORE: 3000,
} as const;

// URLs
export const URLS = {
  HOME: "/",
  ABOUT: "/about",
  PANEL_LOGIN: "/panel/login",
} as const;
