# Kenakata Kit

WooCommerce toolkit for Bangladeshi online shops, by [DigiBhai.com](https://digibhai.com/).
English + বাংলা. Works with any theme; 15+ Elementor widgets.

## ডাউনলোড ও ইনস্টল
1. **[Releases](../../releases/latest)** পাতা থেকে `kenakata-kit-<version>.zip` নামান। সবুজ "Code → Download ZIP" বোতামটা ব্যবহার করবেন না।
2. WordPress → Plugins → Add New → Upload Plugin → zip বেছে নিন → Install → Activate। আগের ভার্সন থাকলে "Replace current" চাপুন।
3. বাম মেনুতে **Kenakata Kit** পাবেন।

একবার ইনস্টল করার পর নতুন ভার্সন এলে WordPress নিজেই **"Update available"** দেখাবে।

## Free আর Pro
| Free | Pro (লাইসেন্স কী লাগবে) |
|---|---|
| প্রোডাক্ট কার্ড, Buy Now, সহজ চেকআউট, ডেলিভারি চার্জ, ভুয়া অর্ডার ঠেকানো, সাইড কার্ট, লাইভ সার্চ, উইশলিস্ট, ছবিসহ রিভিউ, প্রশ্নোত্তর, ফোন দিয়ে অর্ডার ট্র্যাকিং, নিরাপত্তা, সব Elementor উইজেট | স্টোর ড্যাশবোর্ড (`/dashboard`), এক চাপে কুরিয়ার বুকিং (Steadfast, Pathao) আর কুরিয়ার চেক, অসম্পূর্ণ অর্ডার |

**Pro চালু করতে:** DigiBhai.com-এর সাথে যোগাযোগ করুন আর আপনার ওয়েবসাইটের ঠিকানা জানান। কী পাওয়ার পর **Kenakata Kit → License**-এ বসিয়ে দিন।

## Requirements
WordPress 6.3+, WooCommerce 7.0+, PHP 7.4+.

## Development
- Plugin source: `kenakata-kit/`. Dev tools: `dev/` (`cd dev && npm install` once for the PHP linter).
- Release: bump the version in both places in `kenakata-kit/kenakata-kit.php`, add a changelog entry in `kenakata-kit/readme.txt`, commit, then push a tag `vX.Y.Z`. GitHub Actions builds the zip and publishes the release; shops get the update.
- `revoked.json`: IDs of license keys that were taken back (sites check it once a day).

License: GPL-2.0-or-later.
