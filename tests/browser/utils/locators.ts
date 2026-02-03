import { Page } from "@playwright/test";

/**
 * Shared locators for common UI elements across tests.
 * Centralizes selectors to avoid duplication and make updates easier.
 */
export class AppLocators {
  constructor(private page: Page) {}

  get siteTitle() {
    return this.page.locator("[data-test='site-title']");
  }

  get title() {
    return this.page.locator("title");
  }

  get h1() {
    return this.page.locator("h1");
  }

  get pageId() {
    return this.page.locator("[data-test='page-id']");
  }

  get pageText() {
    return this.page.locator("[data-test='page-text']");
  }
}
