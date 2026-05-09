"""
Google Maps Business Scraper — Pan India Lead Generation
Requirements: pip install playwright httpx asyncio
Setup:        playwright install chromium
"""

import asyncio
import json
import re
import httpx
from datetime import datetime
from playwright.async_api import async_playwright

# ─── CONFIG ────────────────────────────────────────────────────────────────────
LARAVEL_API_URL = "http://your-laravel-app.com/api/leads"
LARAVEL_API_KEY = "your-secret-api-key"

CITIES = [
    "Delhi", "Mumbai", "Bangalore", "Hyderabad", "Chennai",
    "Kolkata", "Pune", "Ahmedabad", "Jaipur", "Lucknow",
    "Surat", "Indore", "Bhopal", "Nagpur", "Visakhapatnam"
]

CATEGORIES = [
    "medical clinic",
    "coaching centre",
    "local restaurant",
    "grocery store",
    "hardware shop",
    "travel agency",
    "gym fitness centre",
    "beauty salon",
    "automobile repair shop",
    "real estate agent",
    "clothing shop",
    "electronics store",
]

DAILY_LIMIT = 50   # start small; increase after first week
DELAY_BETWEEN = 4  # seconds between each listing scrape


# ─── HELPERS ───────────────────────────────────────────────────────────────────
def extract_mobile(text: str) -> str | None:
    """Pull a 10-digit Indian mobile number out of any string."""
    pattern = r"(?:\+91[\-\s]?)?[6-9]\d{9}"
    matches = re.findall(pattern, text)
    if matches:
        return re.sub(r"[^\d]", "", matches[0])[-10:]
    return None


async def check_website(url: str) -> bool:
    """Return True if the URL is reachable and returns HTTP 200."""
    if not url or url.strip() == "":
        return False
    try:
        async with httpx.AsyncClient(timeout=8) as client:
            r = await client.get(url, follow_redirects=True)
            return r.status_code == 200
    except Exception:
        return False


async def post_lead(payload: dict) -> bool:
    """Send a single lead to your Laravel API."""
    try:
        async with httpx.AsyncClient(timeout=10) as client:
            r = await client.post(
                LARAVEL_API_URL,
                json=payload,
                headers={
                    "Authorization": f"Bearer {LARAVEL_API_KEY}",
                    "Content-Type": "application/json",
                },
            )
            return r.status_code in (200, 201)
    except Exception as e:
        print(f"  [API ERROR] {e}")
        return False


# ─── SCRAPER ───────────────────────────────────────────────────────────────────
async def scrape_google_maps(city: str, category: str, page, limit: int = 10) -> list[dict]:
    """Search Google Maps and extract business listings."""
    query = f"{category} in {city}"
    url = f"https://www.google.com/maps/search/{query.replace(' ', '+')}"

    print(f"\n[SEARCH] {query}")
    await page.goto(url, wait_until="networkidle", timeout=30000)
    await asyncio.sleep(3)

    results = []
    seen = set()
    scrollable = page.locator('[role="feed"]')

    for _ in range(6):  # scroll a few times to load more listings
        try:
            await scrollable.evaluate("el => el.scrollBy(0, 2000)")
            await asyncio.sleep(2)
        except Exception:
            pass

    listings = await page.locator('[role="feed"] > div[jsaction]').all()
    print(f"  Found {len(listings)} listing elements")

    for item in listings[:limit]:
        try:
            name_el = item.locator("div.qBF1Pd")
            name = (await name_el.inner_text()).strip() if await name_el.count() else None
            if not name or name in seen:
                continue
            seen.add(name)

            # Click to open detail panel
            await item.click()
            await asyncio.sleep(2)

            # Address
            addr_el = page.locator('button[data-item-id="address"]')
            address = (await addr_el.inner_text()).strip() if await addr_el.count() else ""

            # Phone
            phone_el = page.locator('button[data-item-id^="phone"]')
            phone_raw = (await phone_el.inner_text()).strip() if await phone_el.count() else ""
            mobile = extract_mobile(phone_raw)

            # Website
            web_el = page.locator('a[data-item-id="authority"]')
            website = (await web_el.get_attribute("href")).strip() if await web_el.count() else ""

            has_website = await check_website(website)

            lead = {
                "business_name": name,
                "mobile": mobile or "",
                "address": address,
                "city": city,
                "category": category,
                "website": website,
                "has_website": has_website,
                "source": "google_maps",
                "scraped_at": datetime.utcnow().isoformat(),
            }

            results.append(lead)
            print(f"  ✓ {name} | mobile={mobile} | website={'yes' if has_website else 'no'}")
            await asyncio.sleep(DELAY_BETWEEN)

        except Exception as e:
            print(f"  [SKIP] {e}")
            continue

    return results


# ─── MAIN ──────────────────────────────────────────────────────────────────────
async def main():
    total_sent = 0
    all_leads = []

    async with async_playwright() as p:
        browser = await p.chromium.launch(
            headless=True,
            args=["--no-sandbox", "--disable-dev-shm-usage"],
        )
        context = await browser.new_context(
            user_agent=(
                "Mozilla/5.0 (Windows NT 10.0; Win64; x64) "
                "AppleWebKit/537.36 (KHTML, like Gecko) "
                "Chrome/124.0.0.0 Safari/537.36"
            ),
            locale="en-IN",
        )
        page = await context.new_page()

        for city in CITIES:
            for category in CATEGORIES:
                if total_sent >= DAILY_LIMIT:
                    print(f"\n[LIMIT] Daily limit of {DAILY_LIMIT} reached. Stopping.")
                    break

                remaining = DAILY_LIMIT - total_sent
                batch = await scrape_google_maps(city, category, page, limit=min(5, remaining))

                for lead in batch:
                    ok = await post_lead(lead)
                    if ok:
                        total_sent += 1
                        all_leads.append(lead)
                        print(f"  → Posted to API ({total_sent}/{DAILY_LIMIT})")

                if total_sent >= DAILY_LIMIT:
                    break

        await browser.close()

    # Also save locally as backup
    with open("leads_backup.json", "w") as f:
        json.dump(all_leads, f, ensure_ascii=False, indent=2)

    print(f"\n[DONE] Total leads collected and posted: {total_sent}")


if __name__ == "__main__":
    asyncio.run(main())
