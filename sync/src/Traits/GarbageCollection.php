<?php

namespace App\Traits;

trait GarbageCollection
{
    private $gcEnabled = FALSE;
    private $gcMemEnabled = FALSE;

    private function initGc ()
    {
        // Enable garbage collection
        gc_enable();
        $this->gcEnabled = gc_enabled();
        $this->gcMemEnabled = function_exists( 'gc_mem_caches' );
    }

    private function gc()
    {
        pcntl_signal_dispatch();

        if ( $this->gcEnabled ) {
            gc_collect_cycles();

            if ( $this->gcMemEnabled ) {
                gc_mem_caches();
            }
        }
    }
}