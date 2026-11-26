<?php
/** Supply the current locale without bootstrapping a site. */
function evo() { return new class { public function getLocale() { return 'uk'; } }; }
require __DIR__ . '/bootstrap.php';
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Capsule\Manager;
use Seiger\sCommerce\Filter\sFilter;
use Seiger\sCommerce\Models\sAttribute;
$app = new Container(); Facade::setFacadeApplication($app);
$checks=0;
$check=static function($ok,$message) use (&$checks) { if(!$ok) throw new RuntimeException($message); ++$checks; };
$source=file_get_contents(dirname(__DIR__,2).'/src/Filter/sFilter.php');
$start=strpos($source,"        \$valuesQuery = DB::table");
$end=strpos($source,'        // Retrieve attributes',$start);
$queryCode=substr($source,$start,$end-$start);
foreach(['','evo_','shop-'] as $prefix) {
 $capsule=new Manager($app); $capsule->addConnection(['driver'=>'sqlite','database'=>':memory:','prefix'=>$prefix]); $capsule->setAsGlobal(); $capsule->bootEloquent();
 $app->instance('db',$capsule->getDatabaseManager()); Facade::clearResolvedInstance('db');
 $db=$capsule->getConnection();
 $db->getSchemaBuilder()->create('s_attributes',function($t){$t->integer('id');$t->integer('asfilter');});
 $db->getSchemaBuilder()->create('s_product_attribute_values',function($t){foreach(['product','attribute','valueid'] as $c)$t->integer($c);$t->string('value')->nullable();});
 $db->table('s_attributes')->insert([['id'=>1,'asfilter'=>1],['id'=>2,'asfilter'=>1],['id'=>3,'asfilter'=>0]]);
 foreach([[1,1,11,'red'],[1,1,11,'red'],[2,1,11,'red'],[3,1,12,'blue'],[1,2,0,'1'],[2,2,0,'0'],[1,3,11,'red']] as [$product,$attribute,$valueid,$value]) $db->table('s_product_attribute_values')->insert(compact('product','attribute','valueid','value'));
 foreach([[1,2,3],[1],[99],[]] as $productIds) {
  $db->enableQueryLog();$db->flushQueryLog();eval(str_replace('DB::', '\Illuminate\Support\Facades\DB::', $queryCode));
  $check(count($db->getQueryLog())===1,'One aggregate query');
  $actual=$values->map(fn($r)=>(array)$r)->all();
  $legacy=$db->table('s_product_attribute_values')->whereIn('product',$productIds)->whereIn('attribute',[1,2])->select('attribute','value','valueid')->selectRaw('COUNT(DISTINCT product) as count')->groupBy('attribute','value','valueid')->get()->map(fn($r)=>(array)$r)->all();
  $check($actual===$legacy,'Counts, duplicates, checkbox values and product selection preserved for '.$prefix);
 }
}
$filter=(new ReflectionClass(sFilter::class))->newInstanceWithoutConstructor();
(new ReflectionProperty(sFilter::class,'filters'))->setValue($filter,['color'=>['blue']]);
$item=new sAttribute();$item->setRawAttributes(['id'=>1,'alias'=>'color']);
$item->setRelation('values',collect([(object)['avid'=>11,'alias'=>'red','uk'=>'Червоний','base'=>'Red'],(object)['avid'=>12,'alias'=>'blue','base'=>'Blue'],(object)['avid'=>13,'alias'=>'green','base'=>'Green']]));
$counts=collect([(object)['attribute'=>1,'valueid'=>11,'count'=>2],(object)['attribute'=>1,'valueid'=>11,'count'=>9],(object)['attribute'=>2,'valueid'=>11,'count'=>50]]);
$result=(new ReflectionMethod(sFilter::class,'buildSelectFilter'))->invoke($filter,$item,$counts);
$check($result->values->count()===2,'Hide unavailable unselected values');
$check($result->values[0]->count===2,'Preserve first matching count instead of last');
$check($result->values[0]->label==='Червоний','Locale label');
$check($result->values[1]->count===0 && $result->values[1]->checked===1,'Selected zero count remains visible');
$check($result->values[1]->label==='Blue' && $result->values[1]->link==='color=blue','Fallback and link preserved');
echo "$checks filter optimization checks passed\n";