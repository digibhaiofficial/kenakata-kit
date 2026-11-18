# Compile a .po into a .mo without gettext or npm (fallback when php-parser is not installed).
# Usage: python dev/i18n/po2mo.py kenakata-kit/languages/kenakata-kit-bn_BD.po kenakata-kit/languages/kenakata-kit-bn_BD.mo
import re, struct, sys, subprocess, json
po, mo = sys.argv[1], sys.argv[2]
def unq(s):
    return bytes(s, 'utf-8').decode('unicode_escape').encode('latin-1').decode('utf-8')
entries=[]; cur={}; key=None
for line in open(po, encoding='utf-8').read().split('\n'):
    if line.startswith('#') or not line.strip():
        if cur.get('msgid') is not None and key: entries.append(cur); cur={}; key=None
        continue
    m=re.match(r'(msgctxt|msgid_plural|msgid|msgstr(?:\[\d+\])?) "(.*)"$', line)
    if m:
        if m.group(1) in ('msgid','msgctxt') and 'msgstr' in ''.join(cur.keys()):
            entries.append(cur); cur={}
        key=m.group(1); cur[key]=unq(m.group(2)); continue
    m=re.match(r'"(.*)"$', line)
    if m and key: cur[key]+=unq(m.group(1))
if cur.get('msgid') is not None: entries.append(cur)
cat={}
for e in entries:
    mid=e.get('msgid','')
    if 'msgctxt' in e: mid=e['msgctxt']+'\x04'+mid
    if 'msgid_plural' in e:
        strs=[e[k] for k in sorted(k for k in e if k.startswith('msgstr['))]
        if not any(strs): continue
        cat[mid+'\x00'+e['msgid_plural']]='\x00'.join(strs)
    else:
        if not e.get('msgstr') and mid: continue
        cat[mid]=e.get('msgstr','')
keys=sorted(cat.keys(), key=lambda k:k.encode('utf-8'))
ids=b''; strs=b''; offs=[]
for k in keys:
    kb=k.encode('utf-8'); vb=cat[k].encode('utf-8')
    offs.append((len(ids),len(kb),len(strs),len(vb))); ids+=kb+b'\0'; strs+=vb+b'\0'
n=len(keys); ko=7*4; vo=ko+n*8; ido=vo+n*8; so=ido+len(ids)
out=struct.pack('Iiiiiii',0x950412de,0,n,ko,vo,0,0)
for o in offs: out+=struct.pack('ii',o[1],o[0]+ido)
for o in offs: out+=struct.pack('ii',o[3],o[2]+so)
out+=ids+strs
open(mo,'wb').write(out); print(n,'messages')
