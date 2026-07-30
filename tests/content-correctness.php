<?php
declare(strict_types=1);
$core = dirname(__DIR__) . '/_edit/core/';
spl_autoload_register(function($class) use ($core) { $prefix='Edit\\Core\\'; if(str_starts_with($class,$prefix)) require $core.str_replace('\\','/',substr($class,strlen($prefix))).'.php'; });
function now() { return gmdate('Y-m-d H:i:s'); }
function addMediaUrl($row) { return $row + ['url'=>'/_edit/uploads/'.$row['path']]; }
function sendError($message,$code=400) { throw new InvalidArgumentException($message,$code); }
use Edit\Core\ContentTypes\{ContentType,BlockRegistry};
use Edit\Core\Database\Database;
$failures=[]; $checks=0;
function test($name,$fn) { global $failures,$checks; try { $fn(); $checks++; } catch(Throwable $e) { $failures[]="$name: ".$e->getMessage(); } }
function check($ok,$message='Unexpected result') { if(!$ok) throw new RuntimeException($message); }
function rejects($fn) { try { $fn(); } catch(InvalidArgumentException $e) { return; } throw new RuntimeException('Invalid data was accepted'); }
function fixture() {
 $db=new Database(':memory:');
 $fields=[['key'=>'price','type'=>'number','required'=>true,'config'=>['min'=>0]],['key'=>'active','type'=>'boolean','required'=>true],['key'=>'title','type'=>'text'],['key'=>'category','type'=>'select','config'=>['choices'=>['sale'=>'For sale','rent'=>'To rent']]],['key'=>'rows','type'=>'repeater','config'=>['fields'=>[['key'=>'rooms','type'=>'number','required'=>true,'config'=>['min'=>0]]]]],['key'=>'photo','type'=>'media']];
 $schema=['key'=>'property','allow_open'=>true,'fields'=>array_column($fields,null,'key'),'field_groups'=>[['key'=>'details','fields'=>$fields]]];
 $blocks=new BlockRegistry('/nonexistent-fixture');$blocks->load();
 (new ReflectionProperty($blocks,'blocks'))->setValue($blocks,['feature'=>['key'=>'feature','fields'=>[['key'=>'text','type'=>'text','required'=>true]]]]);
 return [$db,$schema,new ContentType($db,'property',$schema,$blocks,false),$blocks];
}
require dirname(__DIR__).'/_edit/api/routes/public.php';
test('PHP bracket filters and numeric ordering',function(){
 [$db,$schema,$type]=fixture();
 foreach([90000,1000000,200000] as $price) $type->create(['slug'=>'p-'.$price,'status'=>'published','fields'=>['details'=>['price'=>$price,'active'=>false]]]);
 parse_str('fields[price_gte]=200000&order_by=price&order_dir=ASC',$query);
 $params=parsePublicQueryParams($query,$schema);
 $rows=$type->all($params+['status'=>'published']);
 check(array_column($rows,'slug')===['p-200000','p-1000000']);
 check($type->count(['field_filters'=>$params['field_filters'],'status'=>'published'])===2,'Count differs');
});
test('Zero survives round trip',function(){ [$db,$schema,$type]=fixture();$id=$type->create(['slug'=>'zero','fields'=>['details'=>['price'=>0,'active'=>false]]]);check($type->find($id)['fields']['details']['price']===0); });
test('Required fields cannot be omitted when publishing',function(){ [$db,$schema,$type]=fixture();rejects(fn()=> $type->create(['slug'=>'missing','status'=>'published'])); });
test('Repeater children validated',function(){ [$db,$schema,$type]=fixture();rejects(fn()=> $type->create(['slug'=>'nested','status'=>'published','fields'=>['details'=>['price'=>1,'active'=>false,'rows'=>[[]]]]])); });
test('Revision restoration',function(){ [$db,$schema,$type]=fixture();$id=$type->create(['slug'=>'original','fields'=>['details'=>['price'=>10,'active'=>false]]]);$type->update($id,['slug'=>'changed']);$r=$type->getRevisions($id)[0];$type->restoreRevision($id,$r['id']);check($type->find($id)['slug']==='original'); });

test('Drafts permit missing required fields, publish-only update does not',function(){
 [$db,$schema,$type]=fixture(); $id=$type->create(['slug'=>'draft']);
 rejects(fn()=> $type->update($id,['status'=>'published']));
 check($type->find($id)['status']==='draft' && $type->getRevisions($id)===[]);
});
test('Full replacement cannot remove required published fields',function(){
 [$db,$schema,$type]=fixture();$id=$type->create(['slug'=>'public','status'=>'published','fields'=>['details'=>['price'=>5,'active'=>false]]]);
 rejects(fn()=> $type->update($id,['fields'=>['details'=>['active'=>false]]]));
 check($type->find($id)['fields']['details']['price']===5 && $type->getRevisions($id)===[]);
});
test('Malformed field values fail even in drafts',function(){
 foreach ([['price'=>'abc'],['price'=>INF],['price'=>-1],['active'=>'truthy'],['title'=>['nested']],['category'=>'invalid'],['unknown'=>'value'],['photo'=>999],['rows'=>[['rooms'=>'abc']]]] as $fields) {
  [$db,$schema,$type]=fixture(); rejects(fn()=> $type->create(['slug'=>'invalid','fields'=>['details'=>$fields]]));check($type->count()===0);
 }
});
test('Select choices and numeric exponents survive',function(){
 [$db,$schema,$type]=fixture();$id=$type->create(['slug'=>'valid','fields'=>['details'=>['price'=>'2e5','category'=>'sale','active'=>'0']]]);
 $fields=$type->find($id)['fields']['details'];check($fields['price']===200000 && $fields['active']===false && $fields['category']==='sale');
});
test('Unknown groups and null fields are rejected',function(){
 [$db,$schema,$type]=fixture();rejects(fn()=> $type->create(['slug'=>'bad','fields'=>['unknown'=>[]]]));rejects(fn()=> $type->create(['slug'=>'bad','fields'=>null]));
});
test('Flexible block child validation and unknown blocks',function(){
 [$db,$schema,$type]=fixture();
 rejects(fn()=> $type->create(['slug'=>'block','status'=>'published','fields'=>['details'=>['price'=>1,'active'=>false],'flexible_content'=>[['block_type'=>'feature','fields'=>[]]]]]));
 rejects(fn()=> $type->create(['slug'=>'block','fields'=>['flexible_content'=>[['block_type'=>'missing','fields'=>[]]]]]));
 $id=$type->create(['slug'=>'block','fields'=>['flexible_content'=>[['block_type'=>'feature','fields'=>['text'=>' Valid ']]]]]);
 check($type->find($id)['fields']['flexible_content'][0]['fields']['text']==='Valid');
});
test('Numeric min max step and required false',function(){
 $number=new Edit\Core\Fields\NumberField();$config=['required'=>true,'config'=>['min'=>0,'max'=>10,'step'=>0.5]];
 check($number->validate(0,$config) && $number->validate(1.5,$config));check(!$number->validate(1.2,$config) && !$number->validate(11,$config));
 check((new Edit\Core\Fields\BooleanField())->validate(false,['required'=>true]));
});
test('Grouped duplicate keys retain their own types',function(){
 [$db,$schema,$old,$blocks]=fixture();
 $schema['field_groups'][]=['key'=>'other','fields'=>[['key'=>'price','type'=>'text']]];
 $type=new ContentType($db,'property',$schema,$blocks,false);
 $id=$type->create(['slug'=>'two','fields'=>['details'=>['price'=>0],'other'=>['price'=>'Negotiable']]]);
 foreach ([$type->find($id),$type->all()[0]] as $item) check($item['fields']['details']['price']===0 && $item['fields']['other']['price']==='Negotiable');
 rejects(fn()=>parsePublicQueryParams(['order_by'=>'price'],$schema));
 check(parsePublicQueryParams(['order_by'=>'details.price'],$schema)['order_by']==='details.price');
});
test('Query values, private fields and injection rejected',function(){
 [$db,$schema,$type]=fixture();$schema['field_groups'][0]['fields'][]=['key'=>'secret','type'=>'number','public'=>false];
 foreach ([['limit'=>'10foo'],['offset'=>-1],['offset'=>1000001],['order_dir'=>'ASC; DROP TABLE content'],['order_by'=>'author_id'],['order_by'=>'details.secret'],['fields'=>['secret'=>1]],['fields'=>['price_gte'=>['nested']]],['fields'=>['price_like'=>'1']],['fields'=>['price_gte'=>'abc']],['fields'=>['rows'=>'1']],['populate'=>['related']]] as $q) rejects(fn()=>parsePublicQueryParams($q,$schema));
 check($db->table('content')->count()===0);
});
test('Numeric pagination is stable and missing values sort last',function(){
 [$db,$schema,$type]=fixture();
 foreach ([10,2,2,null] as $i=>$price) $type->create(['slug'=>'sort-'.$i,'fields'=>['details'=>['price'=>$price]]]);
 foreach (['ASC'=>['sort-1','sort-2','sort-0','sort-3'],'DESC'=>['sort-0','sort-1','sort-2','sort-3']] as $direction=>$expected) {
  $rows=$type->all(['order_by'=>'details.price','order_dir'=>$direction]);check(array_column($rows,'slug')===$expected);
  check($type->all(['order_by'=>'price','order_dir'=>$direction,'limit'=>1,'offset'=>1])[0]['slug']===$expected[1]);
 }
});
test('Malformed legacy numbers are not zero',function(){
 [$db,$schema,$type]=fixture();
 foreach (['bad','zero'] as $slug) $ids[]=$type->create(['slug'=>$slug]);
 $db->table('content_meta')->insert(['content_id'=>$ids[0],'meta_key'=>'details.price','meta_value'=>'not-a-number']);
 $db->table('content_meta')->insert(['content_id'=>$ids[1],'meta_key'=>'details.price','meta_value'=>'0']);
 $params=parsePublicQueryParams(['fields'=>['price'=>0]],$schema);
 check(array_column($type->all($params),'slug')===['zero']);
});
test('Multiple filters use AND and quoted values stay data',function(){
 [$db,$schema,$type]=fixture();
 foreach ([['sale',200000],['rent',1000000],['sale',90000]] as $i=>[$category,$price]) $type->create(['slug'=>'filter-'.$i,'fields'=>['details'=>['price'=>$price,'category'=>$category,'title'=>"O'Brien"]]]);
 $p=parsePublicQueryParams(['fields'=>['price_gte'=>200000,'category'=>'sale','title_like'=>"O'Brien"]],$schema);
 check(array_column($type->all($p),'slug')===['filter-0']);check($type->count($p)===1);
});
test('Revision media IDs, zero and previous version preserved',function(){
 [$db,$schema,$type]=fixture();$media=$db->table('media')->insert(['filename'=>'x.jpg','path'=>'2026/09/x.jpg','mime_type'=>'image/jpeg','size'=>1]);
 $id=$type->create(['slug'=>'before','status'=>'published','fields'=>['details'=>['price'=>0,'active'=>false,'photo'=>['id'=>$media]]]]);
 $before=$type->find($id);$type->update($id,['slug'=>'after','fields'=>['details'=>['price'=>10,'active'=>true]]]);
 $type->restoreRevision($id,$type->getRevisions($id)[0]['id']);$after=$type->find($id);
 check($after['fields']===$before['fields'] && $after['slug']==='before');check(count($type->getRevisions($id))===2);check($type->getRevisions($id)[0]['slug']==='after');
});
test('Incompatible revision fails atomically under current schema',function(){
 [$db,$schema,$type,$blocks]=fixture();$id=$type->create(['slug'=>'before','status'=>'published','fields'=>['details'=>['price'=>0,'active'=>false]]]);$type->update($id,['slug'=>'after']);
 $schema['field_groups'][0]['fields'][]=['key'=>'new-required','type'=>'text','required'=>true];
 $new=new ContentType($db,'property',$schema,$blocks,false);$revisions=$new->getRevisions($id);
 rejects(fn()=> $new->restoreRevision($id,$revisions[0]['id']));check($new->find($id)['slug']==='after');check($new->getRevisions($id)===$revisions);
});
test('Restore cannot use another record or wrong type',function(){
 [$db,$schema,$type,$blocks]=fixture();$id=$type->create(['slug'=>'one']);$other=$type->create(['slug'=>'two']);$type->update($id,['slug'=>'changed']);$revision=$type->getRevisions($id)[0]['id'];
 rejects(fn()=> $type->restoreRevision($other,$revision));
 $wrong=new ContentType($db,'other',$schema,$blocks,false);check($wrong->getRevisions($id)===[]);
 try {$wrong->restoreRevision($id,$revision);throw new RuntimeException('Wrong type restored');} catch(OutOfBoundsException $e) {}
 check($type->find($id)['slug']==='changed');
});
test('Retention keeps newest ten with timestamp ties',function(){
 [$db,$schema,$type]=fixture();$id=$type->create(['slug'=>'version-0']);for($i=1;$i<=12;$i++)$type->update($id,['slug'=>'version-'.$i]);
 $revisions=$type->getRevisions($id);check(count($revisions)===10 && $revisions[0]['revision_number']===12 && $revisions[9]['revision_number']===3);
});

test('Database failure rolls back record, metadata and revision',function(){
 [$db,$schema,$type]=fixture();$id=$type->create(['slug'=>'before','fields'=>['details'=>['price'=>1]]]);$before=$type->find($id);
 $db->execute("CREATE TRIGGER fail_meta BEFORE INSERT ON content_meta BEGIN SELECT RAISE(ABORT, 'fixture failure'); END");
 try {$type->update($id,['slug'=>'after','fields'=>['details'=>['price'=>2]]]);throw new RuntimeException('Failure was not raised');} catch(RuntimeException $e) { check(str_contains($e->getMessage(), 'fixture failure')); }
 check($type->find($id)===$before && $type->getRevisions($id)===[]);
 $db->execute('DROP TRIGGER fail_meta');$type->update($id,['slug'=>'working']);check($type->find($id)['slug']==='working');
});
test('Missing required boolean remains missing in draft storage',function(){
 [$db,$schema,$type]=fixture();$id=$type->create(['slug'=>'unset','fields'=>['details'=>['price'=>0,'active'=>null]]]);
 check($type->find($id)['fields']['details']['active']===null);rejects(fn()=> $type->update($id,['status'=>'published']));
});
test('Datetime bounds and UTC conversion',function(){
 $field=new Edit\Core\Fields\DatetimeField();
 check($field->validate('2026-09-08T12:30',[]));check($field->validate('2026-09-08T12:30:00+02:00',[]));
 check(!$field->validate('2026-02-30T12:30',[]) && !$field->validate('tomorrow',[]));
 check($field->toDatabase('2026-09-08T12:30:00+02:00')==='2026-09-08T10:30:00Z');
});

test('Nested datetime normalization and relationship sanitizer',function(){
 [$db,$schema,$type,$blocks]=fixture();
 $schema['field_groups'][0]['fields'][]=['key'=>'events','type'=>'repeater','config'=>['fields'=>[['key'=>'time','type'=>'datetime']]]];
 $type=new ContentType($db,'property',$schema,$blocks,false);
 $id=$type->create(['slug'=>'nested-time','fields'=>['details'=>['events'=>[['time'=>'2026-09-08T12:30:00+02:00']]]]]);
 check($type->find($id)['fields']['details']['events'][0]['time']==='2026-09-08T10:30:00Z');
 check((new Edit\Core\Fields\RelationshipField())->sanitize(1,[])===1);
});
if($failures){echo implode("\n",$failures)."\n";exit(1);}echo "PASS: $checks content correctness checks\n";
