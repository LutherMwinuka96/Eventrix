import os
import re
import urllib.request

BASE = os.path.join(os.path.dirname(__file__), "..", "assets", "fonts")
os.makedirs(BASE, exist_ok=True)

FAMILIES = [
    ("Inter", "inter", "400;500;600;700"),
    ("Plus Jakarta Sans", "plus-jakarta-sans", "700;800"),
    ("Poppins", "poppins", "300;400;500;600;700;800;900"),
    ("Urbanist", "urbanist", "400;600;700;800"),
]

for name, prefix, weights in FAMILIES:
    url = (
        "https://fonts.googleapis.com/css2?family="
        + name.replace(" ", "+")
        + ":wght@"
        + weights
        + "&display=swap"
    )
    req = urllib.request.Request(url, headers={"User-Agent": "Mozilla/5.0"})
    css = urllib.request.urlopen(req, timeout=30).read().decode()
    blocks = re.split(r"@font-face\s*\{", css)[1:]
    for block in blocks:
        weight_match = re.search(r"font-weight:\s*(\d+)", block)
        url_match = re.search(r"url\((https://fonts\.gstatic\.com/[^)]+)\)", block)
        if not weight_match or not url_match:
            continue
        weight = weight_match.group(1)
        font_url = url_match.group(1)
        fname = f"{prefix}-{weight}.woff2"
        path = os.path.join(BASE, fname)
        urllib.request.urlretrieve(font_url, path)
        print(f"saved {fname} ({os.path.getsize(path)} bytes)")
