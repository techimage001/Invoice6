#!/usr/bin/env python3
"""Escape-aware PHP structure check: brace balance and unterminated strings.
Not a full parser, but it correctly handles \\' and \\" escapes and comments,
which naive scanners get wrong."""
import sys,glob
def check(path):
    raw=open(path,encoding='utf-8',errors='ignore').read()
    # Only inspect regions actually inside PHP tags. Apostrophes in HTML text
    # (for example "user's") are not string delimiters and must be ignored.
    s=''
    pos=0
    while True:
        a=raw.find('<?',pos)
        if a==-1: break
        b=raw.find('?>',a)
        seg = raw[a:b+2] if b!=-1 else raw[a:]
        s+=seg+'\n'
        if b==-1: break
        pos=b+2
    i=0;q=None;line=1;depth=0;opened=None
    while i<len(s):
        c=s[i]
        if c=='\n': line+=1
        if q:
            if c=='\\': i+=2; continue
            if c==q: q=None; opened=None
            i+=1; continue
        if c=='/' and i+1<len(s) and s[i+1]=='/':
            while i<len(s) and s[i]!='\n': i+=1
            continue
        if c=='#':
            while i<len(s) and s[i]!='\n': i+=1
            continue
        if c=='/' and i+1<len(s) and s[i+1]=='*':
            i+=2
            while i+1<len(s) and not(s[i]=='*' and s[i+1]=='/'):
                if s[i]=='\n': line+=1
                i+=1
            i+=2; continue
        if c in ("'",'"'): q=c; opened=line; i+=1; continue
        if c=='{': depth+=1
        elif c=='}': depth-=1
        i+=1
    return depth, (q is not None), opened
bad=0
for f in sorted(glob.glob('*.php')+glob.glob('app_private/*.php')+glob.glob('cron/*.php')):
    d,unterm,opened=check(f)
    if d!=0 or unterm:
        bad+=1
        print(f"FAIL {f}: brace depth {d}"+(f", unterminated string opened line {opened}" if unterm else ""))
print(f"{'ALL PHP FILES OK' if bad==0 else str(bad)+' FILE(S) WITH PROBLEMS'}")
sys.exit(1 if bad else 0)
