<?php

namespace Merkeleon\Log\Console\Commands;

use Illuminate\Console\Command;
use Merkeleon\Log\LogRepository;

class BulkInsertToStorage extends Command
{
    protected $signature = 'merkeleon:log:bulk-insert-to-storage {logName}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Bulk insert logs to storage from bufferFile';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $logName = $this->argument('logName');

        $logRepository = LogRepository::make($logName);

        $logRepository->flushBuffer();
    }
}
