# Kenakata Kit — মালিকের গাইড (শুরু করুন এখান থেকে)

এই গাইড তাঁদের জন্য যাঁরা কোড জানেন না, কিন্তু কম্পিউটার আর ফাইল-ফোল্ডার ভালো বোঝেন।
এর একটা কপি থাকে আপনার কম্পিউটারে `D:\kenakata-kit\00-START-HERE.md` নামে, আরেকটা GitHub-এ `docs/OWNER-GUIDE-bn.md`।

## ১. ফোল্ডারে কী কোথায়

```
D:\kenakata-kit\
├─ 00-START-HERE.md          ← এই গাইড। যেকোনো AI-কে প্রথমে এটা পড়তে বলুন
├─ CLAUDE.md                 ← AI-এর জন্য নির্দেশনা (এই ফোল্ডার খুললে AI নিজেই পড়ে)
├─ PUSH-TO-GITHUB.bat        ← এক ক্লিকে সব পরিবর্তন GitHub-এ পাঠানো
├─ kenakata-kit\             ← প্লাগইনের আসল কোড (GitHub-এর public রিপো, ভার্সন 3.28.0)
│   ├─ kenakata-kit\         ← প্লাগইন নিজে (এটাই zip হয়ে সাইটে যায়)
│   ├─ docs\STATUS.md        ← প্রজেক্টের পুরো অবস্থা: কী আছে, কী বাকি, সিদ্ধান্ত
│   ├─ CLAUDE.md             ← কোড লেখার নিয়ম, টেস্ট, রিলিজের ধাপ
│   ├─ license-panel\        ← লাইসেন্স কী বানানোর প্যানেল (ওয়েবসাইট হিসেবে চলে)
│   └─ dev\                  ← টেস্ট আর বানানোর টুল (সাইটে যায় না)
├─ kenakata-license-panel\   ← প্রাইভেট রিপো: তালাবদ্ধ চাবি, কাস্টমারের খাতা, ব্যবসার হ্যান্ডঅফ
├─ inbox\                    ← ক্লাউডের Claude যে "প্যাকেট" (.bundle) পাঠায়, সেটা এখানে আসে
├─ releases\                 ← চূড়ান্ত zip (kenakata-kit-3.28.0.zip), শুধু রেফারেন্সের জন্য
├─ secrets\                  ← গোপন ফাইল। কখনো কাউকে দেবেন না, GitHub-এ তুলবেন না, চ্যাটে পেস্ট করবেন না
├─ media\                    ← লোগো আর ডিজাইনের ছবি
├─ other-projects\           ← প্লাগইনের বাইরের কাজ (যেমন রূপসীর SEO-র নোট)
└─ _to_delete\               ← পুরনো ভার্সন। দেখে নিশ্চিত হয়ে নিজে মুছে দিন
```

আসল কোডের মালিক GitHub। কম্পিউটারের `kenakata-kit\` আর `kenakata-license-panel\` ফোল্ডার দুটো তার কপি।
কম্পিউটার নষ্ট হলেও GitHub থেকে সব ফিরে পাবেন। শুধু `secrets\` ফোল্ডারটা নিজে আলাদা করে ব্যাকআপ রাখুন
(পেনড্রাইভ বা নিজের Google Drive)।

## ২. নতুন কম্পিউটারে একবারের সেটআপ
1. **GitHub Desktop** ইনস্টল করুন (https://desktop.github.com) আর আপনার GitHub অ্যাকাউন্টে লগইন করুন
   (`digibhaiofficial`)। এর সাথে Git-ও চলে আসে।
2. `D:\kenakata-kit` নামে একটা ফোল্ডার বানান।
3. GitHub Desktop → File → Clone repository → `digibhaiofficial/kenakata-kit` → Local path: `D:\kenakata-kit\kenakata-kit`।
   একইভাবে `digibhaiofficial/kenakata-license-panel` → `D:\kenakata-kit\kenakata-license-panel`।
4. GitHub থেকে `kenakata-kit\docs\OWNER-GUIDE-bn.md` ফাইলটা `D:\kenakata-kit\00-START-HERE.md` নামে কপি করুন,
   আর `kenakata-kit\docs\PUSH-TO-GITHUB.bat` ফাইলটা `D:\kenakata-kit\` ফোল্ডারে কপি করুন।
5. আপনার ব্যাকআপ থেকে `secrets\` ফোল্ডারটা `D:\kenakata-kit\`-এ রাখুন।
6. লাইসেন্স প্যানেল নতুন ব্রাউজারে: `kenakata-license-panel\README.md` দেখুন ("আগের চাবি আছে" → `kk-vault.json` → পাসওয়ার্ড)।

## ৩. AI দিয়ে প্লাগইনে কাজ করানো
যেকোনো AI এজেন্টকে (Claude, অন্য কোনো AI, অন্য অ্যাকাউন্ট) `D:\kenakata-kit` ফোল্ডারটা দিন আর বলুন:
> "প্রথমে 00-START-HERE.md, তারপর kenakata-kit\docs\STATUS.md আর kenakata-kit\CLAUDE.md পড়ো। তারপর <কাজ>।
> আমার সাথে বাংলায় কথা বলো।"

AI দুইভাবে কাজ করতে পারে, দুটোতেই শেষ ধাপ একই:
- **AI সরাসরি এই ফোল্ডারে ফাইল বদলায়** (যেমন Claude Code / Claude Desktop এই কম্পিউটারে): কাজ ও টেস্ট শেষে AI
  ভার্সন বাড়িয়ে ট্যাগ দেবে।
- **AI ক্লাউডে কাজ করে** (যেমন claude.ai): AI একটা প্যাকেট (`kenakata-kit.bundle`) `inbox\` ফোল্ডারে রেখে দেবে।

এরপর আপনি শুধু **`PUSH-TO-GITHUB.bat`-এ ডাবল-ক্লিক** করবেন। এটা:
1. `inbox\`-এ প্যাকেট থাকলে সেটা কোডে মিলিয়ে নেয়;
2. ফোল্ডারে কেউ কিছু বদলে থাকলে সেটা সেভ (commit) করে;
3. GitHub-এ নতুন কিছু থাকলে সেটা নিয়ে আসে;
4. সব GitHub-এ পাঠায়।

শেষে "DONE" দেখালে AI-কে লিখুন: **"হয়েছে"**। নতুন ভার্সন হলে GitHub ~৩০ সেকেন্ডে zip বানিয়ে সই করে রিলিজ দেয়,
আর সব সাইটে WordPress-এ "Update available" আসে (৬ ঘণ্টার মধ্যে, অথবা Dashboard → Updates → Check again)।

## ৪. যা কখনো করবেন না
- সাইটে হাতে zip আপলোড করবেন না। সাইট শুধু GitHub-এর সই করা রিলিজ থেকে আপডেট নেবে।
- `secrets\` বা `kenakata-license-panel\` ফোল্ডারের কিছু কাউকে পাঠাবেন না, চ্যাটে পেস্ট করবেন না।
- `kenakata-license-panel` রিপোটা কখনো public করবেন না।
- লাইসেন্স প্যানেলে নতুন চাবি (সিলমোহর) বানাবেন না, এতে আগের সব কাস্টমারের কী অকেজো হবে।
- পাসওয়ার্ড বা Application Password চ্যাটে লিখবেন না।

## ৫. কিছু ভুল হলে
- বাটে লাল লেখা বা "Something went wrong" এলে উইন্ডোর স্ক্রিনশট AI-কে পাঠান।
- সাইট ভেঙে গেলে: WordPress → Plugins → Kenakata Kit "Deactivate"। এতে দোকান আগের মতো চলবে। তারপর AI-কে জানান।
- আগের ভার্সনে ফিরতে: GitHub → kenakata-kit → Releases থেকে আগের ভার্সনের নাম AI-কে বলুন; AI নতুন ভার্সন নম্বর দিয়ে
  সেই কোড আবার রিলিজ করবে (ভার্সন নম্বর কখনো আগেরটা আবার ব্যবহার হয় না)।
