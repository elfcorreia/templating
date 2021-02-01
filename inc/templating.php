<?php

namespace templating {

    class TemplateNotFoundException extends \Exception { }

    function fetch(string $template_name): string {
        foreach (TEMPLATES_PATHS as $path) {
            $fn = $path.'/'.$template_name;
            if (file_exists($fn)) {            
                return $fn;
            }
        }
        throw new TemplateNotFoundException("template {$template_name} not found!");
    }

    function foo($msg) {
        $f = fopen("php://stdout", "w");
        fprintf($f, $msg);
        fclose($f);
    }    

    function render(string $template_name, array $context = []): string {
        $state = [
            'current' => null,
            'next' => fetch($template_name),
            'modes' => [0],
            'names' => [], 
            'cache' => []
        ];

        extract($context);
        $render = 'templating\\render';
        $block = function ($name) use (&$state) {
            //foo("block ".$state['current'].":".$name."\n");
            array_push($state['names'], $name);            
            array_push($state['modes'], 0);
            ob_start();            
            //foo(print_r($state, true));
        };
        $end_block = function () use (&$state) {
            $current_block_name = end($state['names']);
            //foo("end_block ".$state['current'].":".$current_block_name."\n");            
            $current_mode = $state['modes'][count($state['modes']) - 2];
            if ($current_mode === 1 /*BLOCK_CACHE*/) {
                if (!isset($state['cache'][$current_block_name])) {
                    $state['cache'][$current_block_name] = trim(ob_get_contents());
                    ob_end_clean();
                    //foo("\tsaving ".$state['current'].":{$current_block_name}\n");
                } else {            
                    ob_end_clean();
                    //foo("\tignoring ".$state['current'].":{$current_block_name}\n");
                }
            } else {
                if (isset($state['cache'][$current_block_name])) {
                    ob_clean();
                    echo $state['cache'][$current_block_name];
                    //foo("\tretrieving ".$state['current'].":{$current_block_name}\n");
                } else {
                    //foo("\tflushing\n");
                }
                ob_end_flush();
            }
            array_pop($state['names']);
            array_pop($state['modes']);
            //foo(print_r($state, true));
        };
        $inherits = function ($name) use (&$state) {
            //foo("inherits ".$state['current']." -> ".$name."\n");
            $state['next'] = fetch($name);
            $mode_keys = array_keys($state['modes']);
            $state['modes'][end($mode_keys)] = 1; // BLOCK_CACHE
            //foo(print_r($state, true));
        };
        
        //foo("==> inicío da renderização\n");
        $result = null;
        do {
            $state['current'] = $state['next'];
            $state['next'] = null;
            //foo("processando ".$state['current']."\n");
            $mode_keys = array_keys($state['modes']);
            $last_mode_key = end($mode_keys);
            $state['modes'][$last_mode_key] = 0; // BLOCK_CACHE
            ob_start();
            require $state['current'];
            if ($state['modes'][$last_mode_key] === 0) {
                $state['result'] = ob_get_contents();
                ob_end_clean();                
            }
        } while ($state['next'] !== null);

        //foo(print_r($state, true));
        //foo("==> fim da renderização\n");
        return $state['result'];
    }
}