<?php
declare(strict_types=1);
$core=dirname(__DIR__).'/_edit/core/';
spl_autoload_register(function($class)use($core){$prefix='Edit\\Core\\';if(str_starts_with($class,$prefix))require $core.str_replace('\\','/',substr($class,strlen($prefix))).'.php';});
use Edit\Core\Configuration\{Store,Validator};
use Edit\Core\Security\HtmlSanitizer;
use Edit\Core\ContentTypes\{ContentHtml,BlockRegistry};
function check($ok,$message){if(!$ok)throw new RuntimeException($message);}
function rejects($fn){try{$fn();}catch(InvalidArgumentException $e){return;}throw new RuntimeException('Accepted invalid input');}
$root=sys_get_temp_dir().'/edit-html-config-'.bin2hex(random_bytes(8));mkdir($root);
try {
 $payloads=['<script>alert(1)</script><p onclick="evil()">ok</p>','<a href="jav&#x61;script:evil()">ok</a>','<a href="java&#10;script:evil()">ok</a>','<svg onload="evil()"><circle/></svg>','<math><mtext><img src=x onerror=evil()></mtext></math>','<iframe srcdoc="evil()"></iframe><object data=x></object>','<img src="data:image/svg+xml,evil()">','<p style="background-image:url(javascript:evil());width:expression(evil())">ok</p>','<form><input name=attributes><button formaction="javascript:evil()">ok</button></form>'];
 foreach($payloads as $html){$clean=HtmlSanitizer::clean($html);check(!preg_match('/<\/?(?:script|svg|math|iframe|object|form|input|button)\b|\bon\w+\s*=|javascript:|expression\(|data:image/i',$clean),'Unsafe HTML survived: '.$clean);check(HtmlSanitizer::clean($clean)===$clean,'Not idempotent');}
 $safe=HtmlSanitizer::clean('<p class="ql-align-center fixed"><strong>Hello</strong> <a href="https://example.invalid">link</a></p><ol><li data-list="bullet">Item</li></ol>');
 check(str_contains($safe,'ql-align-center')&&!str_contains($safe,'fixed')&&str_contains($safe,'data-list="bullet"')&&str_contains($safe,'https://example.invalid'),'Formatting lost');
 $definitions=[['key'=>'body','type'=>'html'],['key'=>'rows','type'=>'repeater','config'=>['fields'=>[['key'=>'body','type'=>'wysiwyg']]]]];
 $schema=['field_groups'=>[['key'=>'details','fields'=>$definitions]]];
 $dirty='<p onclick="evil()">Hello<script>evil()</script></p>';
 $values=['details'=>['body'=>$dirty,'rows'=>[['body'=>$dirty]],'text'=>$dirty]];
 $clean=ContentHtml::clean($values,$schema,null);
 check($clean['details']['body']==='<p>Hello</p>'&&$clean['details']['rows'][0]['body']==='<p>Hello</p>'&&$clean['details']['text']===$dirty,'Nested HTML cleaning');
 $module=['post_types'=>[['key'=>'property','label'=>'Property','label_plural'=>'Properties']],'field_groups'=>[['key'=>'details','title'=>'Details','locations'=>['property'],'fields'=>$definitions]]];
 $json=json_encode($module);$store=new Store($root);
 check(Store::resolve($root)===$root,'Legacy snapshot');
 $first=$store->apply(['modules/property.json'=>$json]);
 $pointer=file_get_contents($root.'/.active.json');$active=$root.'/.versions/'.$first['generation'];
 check(Store::files($active)===['modules/property.json'=>$json],'Published snapshot');
 check(Store::resolve($root)===$root,'Request snapshot changed');
 check(is_file($root.'/backups/'.$first['backup']),'Missing initial backup');
 rejects(fn()=> $store->apply(['modules/property.json'=>$json,'blocks/bad.json'=>'{']));
 check(file_get_contents($root.'/.active.json')===$pointer&&Store::files($active)===['modules/property.json'=>$json],'Partial invalid import');
 rejects(fn()=> $store->apply(['modules/duplicate.json'=>$json]));
 $group=['key'=>'shared','title'=>'Shared','locations'=>['property'],'fields'=>[]];
 $second=$store->apply(['field-groups/shared.json'=>json_encode($group)]);
 check(Store::readZip($root.'/backups/'.$second['backup'])===['modules/property.json'=>$json],'Backup contents');
 rejects(fn()=> $store->apply(['modules/property.json'=>null]));
 $pointer=file_get_contents($root.'/.active.json');
 rename($root.'/backups',$root.'/saved-backups');file_put_contents($root.'/backups','blocked');
 set_error_handler(static fn()=>true);
 try{$store->apply(['blocks/valid.json'=>json_encode(['key'=>'valid','label'=>'Valid','fields'=>[['key'=>'text','type'=>'text']]])]);throw new LogicException('Missing backup accepted');}catch(RuntimeException $e){}finally{restore_error_handler();}
 check(file_get_contents($root.'/.active.json')===$pointer,'Failed backup changed pointer');
 unlink($root.'/backups');rename($root.'/saved-backups',$root.'/backups');
 $bad=$root.'/bad.zip';$zip=new ZipArchive();$zip->open($bad,ZipArchive::CREATE);$zip->addFromString('../escape.json',$json);$zip->close();rejects(fn()=>Store::readZip($bad));unlink($bad);
 $zip=new ZipArchive();$zip->open($bad,ZipArchive::CREATE);$zip->addFromString('modules/link.json',$json);$zip->setExternalAttributesName('modules/link.json',ZipArchive::OPSYS_UNIX,0120777<<16);$zip->close();rejects(fn()=>Store::readZip($bad));
 $badModule=$module;$badModule['field_groups'][0]['fields'][1]['config']['fields'][]=['key'=>'body','type'=>'text'];rejects(fn()=>Validator::validate(['modules/property.json'=>json_encode($badModule)]));
 echo "PASS: malicious HTML corpus, formatting, idempotence, nested legacy HTML, atomic config publication, snapshot isolation, dependent deletion, mandatory backups, invalid ZIP paths/symlinks and duplicate definitions\n";
} finally {
 foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST) as $entry)$entry->isDir()?rmdir($entry->getPathname()):unlink($entry->getPathname());rmdir($root);
}
