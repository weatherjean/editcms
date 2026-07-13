<?php
declare(strict_types=1);
namespace Edit\Core\Configuration;

final class Validator
{
    private const RESERVED=['auth','users','media','config','email-settings','email-logs','email-test','send-email','captcha','post-types','field-groups','blocks','health','public'];
    public static function validate(array $files): void
    {
        $types=[];$groups=[];$blocks=[];
        foreach ($files as $path=>$json) {
            if (!preg_match('#^(modules|field-groups|blocks)/[a-z0-9_-]+\.json$#',$path,$match)) self::fail($path,'invalid config path');
            try {$data=json_decode($json,true,32,JSON_THROW_ON_ERROR);}catch(\JsonException $e){self::fail($path,'invalid JSON');}
            if (!is_array($data) || array_is_list($data)) self::fail($path,'must be an object');
            if ($match[1]==='modules') {
                foreach (['post_types','field_groups'] as $key) if (!is_array($data[$key] ?? null) || !array_is_list($data[$key])) self::fail($path,"{$key} must be a list");
                foreach ($data['post_types'] as $type) {
                    self::definition($type,$path,['key','label','label_plural']);
                    if (!preg_match('/^[a-z][a-z_-]*$/',$type['key']) || in_array($type['key'],self::RESERVED,true)) self::fail($path,'invalid or reserved post type');
                    self::unique($types,$type,$path);
                }
                foreach ($data['field_groups'] as $group) self::unique($groups,$group,$path);
            } elseif ($match[1]==='field-groups') self::unique($groups,$data,$path);
            else self::unique($blocks,$data,$path);
        }
        foreach ($groups as [$group,$path]) {
            self::definition($group,$path,['key','title']);
            if (!is_array($group['locations'] ?? null) || !array_is_list($group['locations'])) self::fail($path,'locations must be a list');
            foreach ($group['locations'] as $location) if (!is_string($location) || !isset($types[$location])) self::fail($path,'unknown location');
            self::fields($group['fields'] ?? null,$path,$types,0);
        }
        foreach ($blocks as [$block,$path]) {
            self::definition($block,$path,['key','label']);
            if (empty($block['fields'])) self::fail($path,'block requires fields');
            self::fields($block['fields'],$path,$types,0);
        }
    }
    private static function unique(array &$map,mixed $definition,string $path): void
    {
        self::definition($definition,$path,['key']);
        if (isset($map[$definition['key']])) self::fail($path,'duplicate definition: '.$definition['key']);
        $map[$definition['key']]=[$definition,$path];
    }
    private static function definition(mixed $value,string $path,array $required): void
    {
        if (!is_array($value) || array_is_list($value)) self::fail($path,'definition must be an object');
        foreach ($required as $key) if (!is_string($value[$key] ?? null) || trim($value[$key])==='') self::fail($path,"{$key} must be a nonempty string");
        if (!preg_match('/^[a-z][a-z0-9_-]*$/',$value['key'])) self::fail($path,'invalid key');
        foreach (['public','required','allow_open','singleton'] as $key) if (isset($value[$key]) && !is_bool($value[$key])) self::fail($path,"{$key} must be boolean");
    }
    private static function fields(mixed $fields,string $path,array $types,int $depth): void
    {
        if ($depth>12 || !is_array($fields) || !array_is_list($fields)) self::fail($path,'fields must be a list with at most 12 levels');
        $keys=[];
        foreach ($fields as $field) {
            self::definition($field,$path,['key','type']);
            if (isset($keys[$field['key']])) self::fail($path,'duplicate field: '.$field['key']);
            $keys[$field['key']]=true;
            if (!in_array($field['type'],['text','textarea','wysiwyg','html','number','boolean','select','date','datetime','slug','media','relationship','repeater','flexible_content'],true)) self::fail($path,'unsupported field type');
            $config=$field['config'] ?? [];
            if (!is_array($config)) self::fail($path,'field config must be an object');
            if (isset($config['multiple']) && !is_bool($config['multiple'])) self::fail($path,'multiple must be boolean');
            if ($field['type']==='number') {
                foreach (['min','max','step'] as $key) if (isset($config[$key]) && $config[$key]!=='' && (!is_numeric($config[$key]) || !is_finite((float)$config[$key]))) self::fail($path,'invalid numeric bound');
                if (isset($config['min'],$config['max']) && $config['min']!=='' && $config['max']!=='' && $config['min']>$config['max']) self::fail($path,'min exceeds max');
                if (isset($config['step']) && $config['step']!=='' && $config['step']<=0) self::fail($path,'step must be positive');
            }
            if ($field['type']==='select' && isset($config['choices']) && !is_array($config['choices'])) self::fail($path,'choices must be an object');
            if ($field['type']==='relationship' && !empty($config['post_type']) && (!is_string($config['post_type']) || !isset($types[$config['post_type']]))) self::fail($path,'unknown relationship post type');
            if ($field['type']==='repeater') self::fields($config['fields'] ?? $field['fields'] ?? null,$path,$types,$depth+1);
        }
    }
    private static function fail(string $path,string $reason): never { throw new \InvalidArgumentException("{$path}: {$reason}"); }
}
