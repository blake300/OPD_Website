const { test, expect } = require('@playwright/test');

async function hasLocator(locator) {
  return (await locator.count()) > 0;
}

test('home page loads', async ({ page }) => {
  await page.goto('/');
  await expect(page.locator('body')).toBeVisible();
});

test('cart page layout', async ({ page }) => {
  await page.goto('/cart.php');

  const emptyCart = page.locator('.empty-cart');
  if (await hasLocator(emptyCart)) {
    await expect(emptyCart).toContainText('Your cart is empty');
    return;
  }

  await expect(page.locator('.cart-panel-header')).toBeVisible();
  await expect(page.locator('.cart-panel-title')).toContainText('Shopping Cart');
  await expect(page.getByRole('link', { name: /Continue Shopping/i })).toBeVisible();
  await expect(page.locator('.cart-groups-header h3')).toContainText(/Accounting Groups/i);
});

test('checkout page renders shipping + card fields when form available', async ({ page }) => {
  await page.goto('/checkout.php?guest=1');

  const form = page.locator('#checkout-form');
  if (!(await hasLocator(form))) {
    await expect(page.locator('.notice')).toContainText(/cart is empty/i);
    return;
  }

  const shippingOptions = page.locator('#checkout-shipping-options .radio-row');
  expect(await shippingOptions.count()).toBeGreaterThanOrEqual(3);

  const cardNumber = page.locator('#card-number-element');
  if (await hasLocator(cardNumber)) {
    await expect(cardNumber).toBeVisible();
    await expect(page.locator('#card-expiry-element')).toBeVisible();
    await expect(page.locator('#card-cvc-element')).toBeVisible();
  }
});

test('favorites signed-out prompt appears when available', async ({ page }) => {
  await page.goto('/products.php');

  const favButton = page.locator('[data-favorite]').first();
  if (!(await hasLocator(favButton))) {
    test.skip(true, 'No favorite buttons found on the products page.');
  }

  await favButton.click();

  const inlineMsg = page.locator('[data-favorite-message]');
  if (await hasLocator(inlineMsg)) {
    await expect(inlineMsg.filter({ hasText: /Please Sign-In to Select Favorites/i }).first()).toBeVisible();
    return;
  }

  await expect(page.locator('#favorite-message')).toContainText(/Please Sign-In to Select Favorites/i);
});
