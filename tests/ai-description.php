<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') exit;
require dirname(__DIR__).'/app/bootstrap.php';
$fixture=json_decode(file_get_contents(ROOT.'/tests/artifacts/ai-description-fixture.json'),true,512,JSON_THROW_ON_ERROR);
$checks=[];
function verifyAi(bool $ok,string $name): void { global $checks; if(!$ok) throw new RuntimeException($name); $checks[]=$name; }
function rejectsAi(callable $call,string $name,?int $code=null): void { try { $call(); } catch(DomainException $exception) { verifyAi($code===null || $exception->getCode()===$code,$name); return; } throw new RuntimeException('Expected rejection: '.$name); }
$key='qa-only-fake-api-key-'.$fixture['token'];
saveAiSettings(['enabled'=>false,'model'=>'glm-4.7-flash','daily_limit'=>20,'api_key'=>$key]);
$raw=file_get_contents(ROOT.'/storage/ai/settings.json'); verifyAi(!str_contains($raw,$key) && isset(json_decode($raw,true)['secret']),'API key encrypted at rest');
verifyAi(aiSettings()['api_key']===$key,'API key decrypts correctly');
saveAiSettings(['enabled'=>false,'model'=>'custom-model-qa','daily_limit'=>9,'api_key'=>'']); verifyAi(aiSettings()['api_key']===$key && aiSettings()['model']==='custom-model-qa','blank key preserved and custom model saved');
rejectsAi(fn()=>saveAiSettings(['model'=>'bad model','daily_limit'=>20]),'invalid model rejected');
rejectsAi(fn()=>saveAiSettings(['model'=>'glm-4.7','daily_limit'=>0]),'invalid daily limit rejected');
rejectsAi(fn()=>saveAiSettings(['model'=>'glm-4.7','daily_limit'=>20,'api_key'=>"bad\r\nkey"]),'header injection rejected');
$service=aiDescriptionPayload('郴州空调清洗维修服务','service','glm-4.7-flash'); $request=aiDescriptionPayload('郴州空调不制冷需要维修','request','glm-5.3');
verifyAi(str_contains($service['messages'][1]['content'],'维修师傅或服务商') && str_contains($request['messages'][1]['content'],'需要维修的客户'),'service and request prompts differ');
verifyAi(str_contains($service['messages'][1]['content'],'郴州空调清洗维修服务'),'title is generation source');
verifyAi(str_contains($service['messages'][0]['content'],'不编造电话号码'),'prompt avoids invented contact and promises');
verifyAi($service['thinking']['type']==='disabled' && $request['thinking']['type']==='enabled' && $request['reasoning_effort']==='low','model-specific thinking controls');
verifyAi(!isset(aiDescriptionPayload('郴州空调清洗维修服务','service','glm-4-flash-250414')['thinking']),'legacy model excludes unsupported thinking parameter');
rejectsAi(fn()=>aiDescriptionPayload('短','service','glm-4.7'),'short title rejected');
rejectsAi(fn()=>aiDescriptionPayload(str_repeat('长',121),'service','glm-4.7'),'long title rejected');
rejectsAi(fn()=>aiDescriptionPayload('郴州空调清洗维修服务','wrong','glm-4.7'),'invalid service type rejected',422);
$response=fn($text,$finish='stop')=>['status'=>200,'body'=>json_encode(['choices'=>[['message'=>['content'=>$text],'finish_reason'=>$finish]]],JSON_UNESCAPED_UNICODE)];
verifyAi(aiDescriptionText($response('<think>internal</think><b>提供空调清洗及常见故障检修服务。</b>'))==='提供空调清洗及常见故障检修服务。','response removes thinking and HTML');
rejectsAi(fn()=>aiDescriptionText(['status'=>401,'body'=>$key]),'provider auth error hides raw key',503);
rejectsAi(fn()=>aiDescriptionText(['status'=>429,'body'=>'quota']),'provider rate error handled',429);
rejectsAi(fn()=>aiDescriptionText(['status'=>500,'body'=>$key]),'provider error hides raw response',502);
rejectsAi(fn()=>aiDescriptionText(['status'=>200,'body'=>'{broken']),'malformed response handled',502);
rejectsAi(fn()=>aiDescriptionText($response('')),'empty response handled',502);
rejectsAi(fn()=>aiDescriptionText($response('提供空调清洗及常见故障检修服务。','length')),'truncated response rejected',502);
rejectsAi(fn()=>aiDescriptionText($response(str_repeat('长',20001))),'oversized description rejected',502);
$usage=ROOT.'/storage/ai/usage-'.$fixture['user'].'.json'; $now=time();
for($i=0;$i<3;$i++) aiReserveUsage($fixture['user'],4,$now);
rejectsAi(fn()=>aiReserveUsage($fixture['user'],4,$now),'per-minute generation limit',429);
aiReserveUsage($fixture['user'],4,$now+61);
rejectsAi(fn()=>aiReserveUsage($fixture['user'],4,$now+122),'daily generation limit',429);
aiReserveUsage($fixture['user'],4,$now+86400); verifyAi(json_decode(file_get_contents($usage),true)['count']===1,'daily quota resets');
echo 'Cleanup file: '.$usage."\n"; unlink($usage);
// Leave generation enabled only with an invalid QA key; UI tests mock generation.
saveAiSettings(['enabled'=>true,'model'=>'glm-4.7-flash','daily_limit'=>20,'api_key'=>'']);
file_put_contents(ROOT.'/tests/artifacts/ai-description-server-results.json',json_encode(['passed'=>count($checks),'checks'=>$checks],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
echo count($checks)." AI server checks passed.\n";
