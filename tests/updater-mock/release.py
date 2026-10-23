# usage: release.py <tag> <zip|-> [--sha body|asset|none|bad] [--name N] [--prerelease]
import json,sys,hashlib,os,shutil
S=os.path.dirname(os.path.abspath(__file__))+'/state'; os.makedirs(S+'/assets',exist_ok=True)
tag=sys.argv[1]; z=sys.argv[2]; a=sys.argv[3:]; mode=a[a.index('--sha')+1] if '--sha' in a else 'body'
name=a[a.index('--name')+1] if '--name' in a else 'FTT '+tag
ver=tag.lstrip('vV'); zn=f'film-transfer-toolkit-{ver}.zip'; assets=[]; body='Test release.\n'
if z!='-':
  shutil.copy(z,S+'/assets/101'); h=hashlib.sha256(open(z,'rb').read()).hexdigest()
  assets.append({'id':101,'name':zn,'size':os.path.getsize(z)})
  if mode=='body': body+=f'sha256: {h}\n'
  if mode=='bad': body+='sha256: '+'0'*64+'\n'
  if mode=='asset':
    open(S+'/assets/102','w').write(f'{h}  {zn}\n'); assets.append({'id':102,'name':'SHA256SUMS.txt','size':80})
json.dump({'tag_name':tag,'name':name,'draft':False,'prerelease':'--prerelease' in a,'html_url':'https://github.com/x/'+tag,'published_at':'2026-10-23T20:00:00Z','body':body,'assets':assets},open(S+'/latest.json','w'))
