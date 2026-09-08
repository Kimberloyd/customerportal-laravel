import { defineConfig, devices } from 'playwright/test';

export default defineConfig({
    testDir: './tests/e2e',
    timeout: 30_000,
    use: {
        baseURL: process.env.PLAYWRIGHT_BASE_URL ?? 'http://localhost:8180',
        trace: 'retain-on-failure',
    },
    projects: [
        { name: 'desktop-chrome', use: { ...devices['Desktop Chrome'], channel: 'chrome' } },
        { name: 'mobile-chrome', use: { ...devices['Pixel 5'], channel: 'chrome' } },
    ],
});
