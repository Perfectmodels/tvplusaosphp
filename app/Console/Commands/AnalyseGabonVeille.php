<?php

namespace App\Console\Commands;

use App\Services\GabonVeilleAgent;
use Illuminate\Console\Command;

class AnalyseGabonVeille extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'veille:gabon-analyse {--limit= : Nombre maximal d’articles pour ce passage}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prépare la veille gabonaise avec le fournisseur IA du remix, sans publier ni modifier les articles sources';

    /**
     * Execute the console command.
     */
    public function handle(GabonVeilleAgent $agent): int
    {
        $result = $agent->run($this->option('limit') !== null ? max(0, (int) $this->option('limit')) : null);
        $this->line(json_encode($result, JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }
}
