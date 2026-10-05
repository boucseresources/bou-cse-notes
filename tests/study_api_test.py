"""Run only against an isolated local database with local mail logs."""
import pathlib
# Reuse the established client helpers without running the unrelated suite.
source=pathlib.Path(__file__).with_name('api_test.py').read_text();exec(source[:source.index('alice=Client();')])
alice=Client();bob=Client();register(alice,'study-alice');register(bob,'study-bob')
_,course=alice.call('study_save',{'kind':'course','title':'Algorithms','subject':'Data Structures','total':40,'done':18,'hours':2,'code':'CSE-212','url':'https://example.com/algorithms'})
assert course['progress']['done']==18
bob.call('study_save',{'kind':'course','id':course['id'],'title':'Stolen','total':40,'done':40},404)
alice.call('study_save',{'kind':'course','id':course['id'],'title':'Algorithms','total':40,'done':41},400)
alice.call('study_save',{'kind':'course','title':'Bad URL','total':1,'done':0,'url':'javascript:alert(1)'},400)
alice.call('study_save',{'kind':'course','title':'CSRF','total':1,'done':0},419,csrf=False)
_,goal=alice.call('study_save',{'kind':'goal','title':'Complete Lab 1','done':False,'due':'2026-10-31'})
alice.call('study_save',{'kind':'goal','title':'Invalid Date','due':'2026-02-31'},400)
alice.call('study_save',{'kind':'goal','id':goal['id'],'expected_revision':goal['revision'],'title':goal['title'],'done':True})
alice.call('study_save',{'kind':'goal','id':goal['id'],'expected_revision':goal['revision'],'title':goal['title'],'done':False},409)
_,summary=alice.call('study');assert summary['open_goals']==0 and summary['in_progress']==1 and summary['completed']==0
_,other=bob.call('study');assert not other['courses'] and not other['goals']
alice.call('coding_tick',{'id':course['id']},400)
_,code=alice.call('save',{'kind':'code','title':'Lab Code','content':'int main() { return 0; }'})
bob.call('coding_tick',{'id':code['id']},404)
alice.call('coding_tick',{'id':code['id']});alice.call('coding_tick',{'id':code['id']})
_,ticks=alice.call('study');assert not ticks['coding'] # Immediate requests cannot inflate recorded time.
alice.call('trash',{'id':course['id']});_,summary=alice.call('study');assert not summary['courses']
alice.call('restore',{'id':course['id']});_,summary=alice.call('study');assert len(summary['courses'])==1
print('PASS: study ownership, CSRF, module counts, URL/date validation, stale revisions, goal completion, private summaries, coding heartbeat ownership/cooldown, Trash/restore.')
