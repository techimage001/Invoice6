#!/usr/bin/env python3
"""Measure Jaccard similarity across all indexable pages.
Ceiling is 0.72 - any pair above it is too similar and must be rewritten."""
import re,glob,itertools,sys

CEILING=0.72
def visible_text(path):
    s=open(path,encoding='utf-8',errors='ignore').read()
    s=re.sub(r'<\?php.*?\?>','',s,flags=re.S)      # drop php blocks
    s=re.sub(r'<\?=.*?\?>','',s,flags=re.S)
    s=re.sub(r'<script.*?</script>','',s,flags=re.S)
    s=re.sub(r'<style.*?</style>','',s,flags=re.S)
    s=re.sub(r'<[^>]+>',' ',s)                      # strip tags
    s=re.sub(r'&[a-z#0-9]+;',' ',s)
    return s.lower()

def tokens(t):
    return set(w for w in re.findall(r"[a-z']{3,}",t))

# Template pages render from the registry, so read their content from bootstrap
def registry_texts():
    b=open('app_private/bootstrap.php',encoding='utf-8').read()
    out={}
    for m in re.finditer(r"'(?P<key>[a-z-]+)'=>\['slug'=>'(?P<slug>[a-z-]+)'.*?'answer'=>'(?P<ans>.*?)',\s*'when'=>'(?P<when>.*?)',\s*'fields'=>\[(?P<fields>.*?)\],\s*'faqs'=>\[(?P<faqs>.*?)\]\]",b,re.S):
        key,slug,ans,when,fields,faqs=m.group('key'),m.group('slug'),m.group('ans'),m.group('when'),m.group('fields'),m.group('faqs')
        text=' '.join([ans,when,fields,faqs])
        text=re.sub(r"[\\'\[\]]",' ',text).lower()
        out[slug+'.php']=text
    return out

pages={}
reg=registry_texts()
pages.update(reg)
for f in sorted(glob.glob('*.php')):
    if f.replace('.php','')+'.php' in pages: continue
    # Only measure INDEXABLE pages. Logged-in app screens call page_header(...) with $app=true,
    # which emits noindex, so they are excluded by definition.
    raw=open(f,encoding='utf-8',errors='ignore').read()
    if 'require_login()' in raw: continue
    if re.search(r"page_header\([^)]*?,\s*false", raw) is None and 'render_template_page' not in raw: continue
    if f.endswith('-template.php'): continue
    t=visible_text(f)
    if len(t.split())>60: pages[f]=t

names=sorted(pages)
tok={n:tokens(pages[n]) for n in names}
pairs=[]
for a,b in itertools.combinations(names,2):
    ta,tb=tok[a],tok[b]
    if not ta or not tb: continue
    j=len(ta&tb)/len(ta|tb)
    pairs.append((j,a,b))
pairs.sort(reverse=True)

print(f"Pages measured: {len(names)}")
print(f"Pairs compared: {len(pairs)}")
if pairs:
    vals=[p[0] for p in pairs]
    print(f"Highest Jaccard similarity: {vals[0]:.4f}  ({pairs[0][1]} vs {pairs[0][2]})")
    print(f"Mean Jaccard similarity:    {sum(vals)/len(vals):.4f}")
    over=[p for p in pairs if p[0]>CEILING]
    print(f"Pairs over the {CEILING} ceiling: {len(over)}")
    for j,a,b in over[:10]: print(f"   OVER {j:.4f}  {a}  vs  {b}")
    print("\nTop 10 most similar pairs:")
    for j,a,b in pairs[:10]: print(f"  {j:.4f}  {a}  vs  {b}")
    sys.exit(1 if over else 0)
