#!/usr/bin/env python3
"""Audit meta descriptions, titles and AEO structure across indexable pages."""
import re,glob
b=open('app_private/bootstrap.php',encoding='utf-8').read()
issues=[]

# 1) Registry-driven template pages: description derives from answer, trimmed to 152
descs={}
for m in re.finditer(r"'([a-z-]+)'=>\['slug'=>'([a-z-]+)','label'=>'([^']*)','title'=>'([^']*)','kw'=>'([^']*)','h1'=>'([^']*)',\s*'answer'=>'(.*?)',",b,re.S):
    key,slug,label,title,kw,h1,ans=m.groups()
    ans_clean=re.sub(r"\\'","'",ans)
    d=ans_clean[:152]
    d=d[:d.rfind(' ')] if ' ' in d else d
    descs[slug]=(title,kw,h1,d,ans_clean)

print("=== TEMPLATE PAGES (registry-driven) ===")
for slug,(title,kw,h1,d,ans) in sorted(descs.items()):
    probs=[]
    if len(d)>155: probs.append(f"desc {len(d)} chars")
    if not h1.lower().startswith(kw.split()[0].lower()) and kw.split()[0].lower() not in h1.lower()[:30]:
        probs.append("H1 does not lead with keyword")
    if kw.split()[0].lower() not in ans.lower()[:80]: probs.append("answer block does not lead with keyword")
    if len(ans.split())<35 or len(ans.split())>95: probs.append(f"answer {len(ans.split())} words (want 40-90)")
    if len(slug)>60: probs.append("slug too long")
    if probs: issues.append((slug,probs))
print(f"  {len(descs)} template pages checked, {len([1 for s,_ in issues if s in descs])} with issues")

# 2) Hand-written pages: check description length passed to page_header
print("\n=== HAND-WRITTEN PAGES ===")
for f in sorted(glob.glob('*.php')):
    raw=open(f,encoding='utf-8',errors='ignore').read()
    if 'require_login()' in raw or 'render_template_page' in raw: continue
    m=re.search(r"page_header\('([^']*)',\s*false\s*,\s*'([^']*)'",raw)
    if not m: continue
    title,desc=m.groups()
    probs=[]
    if len(desc)>155: probs.append(f"desc {len(desc)} chars")
    if 'answer-block' not in raw: probs.append("no answer-first block")
    h2s=re.findall(r'<h2>([^<]*)</h2>',raw)
    if not any('?' in h for h in h2s): probs.append("no question-shaped H2")
    if not re.search(r'<ol|<table|faq_block|answer-block',raw): probs.append("no extractable structure")
    print(f"  {f:44s} desc={len(desc):3d}  H2s={len(h2s):2d}  {'OK' if not probs else '; '.join(probs)}")
    if probs: issues.append((f,probs))

print(f"\n=== TOTAL PAGES WITH ISSUES: {len(issues)} ===")
for name,probs in issues: print(f"  {name}: {'; '.join(probs)}")
