<?php
declare(strict_types=1);
namespace Edit\Core\Security;

final class HtmlSanitizer
{
    private static ?\HTMLPurifier $purifier = null;

    public static function clean(string $html): string
    {
        if ($html === '') return '';
        if (self::$purifier === null) {
            require_once dirname(__DIR__) . '/ThirdParty/HTMLPurifier/library/HTMLPurifier.auto.php';
            $config = \HTMLPurifier_Config::createDefault();
            $config->set('Core.Encoding','UTF-8');
            $config->set('Cache.DefinitionImpl',null);
            $config->set('HTML.Allowed','p[class|style],br,strong,b,em,i,u,s,a[href|title],ul,ol,li[class|data-list],h1,h2,h3,h4,h5,h6,blockquote,code,pre[class],span[class|style],div[class],img[src|alt|title|width|height],table,thead,tbody,tr,th[colspan|rowspan],td[colspan|rowspan],sub,sup');
            $config->set('URI.AllowedSchemes',['http'=>true,'https'=>true,'mailto'=>true,'tel'=>true]);
            $config->set('CSS.AllowedProperties',['color','background-color','text-align','font-weight','font-style','text-decoration','font-family','font-size']);
            $classes = ['ql-align-center','ql-align-right','ql-align-justify','ql-direction-rtl','ql-size-small','ql-size-large','ql-size-huge','ql-font-serif','ql-font-monospace','ql-code-block','ql-code-block-container'];
            for ($i=1;$i<=8;$i++) $classes[]='ql-indent-'.$i;
            $config->set('Attr.AllowedClasses',$classes);
            $definition = $config->getHTMLDefinition(true);
            $definition->addAttribute('li','data-list','Enum#ordered,bullet,checked,unchecked');
            self::$purifier = new \HTMLPurifier($config);
        }
        return self::$purifier->purify($html);
    }
}
