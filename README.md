# Laravel log
Laravel module for logs generating 

## Installation
First, require the package using Composer:

`composer require merkeleon/laravel-log`

1. Add new class Log that should extend Merkeleon\Log\Model\Log

0. Custom your log class.

    - Add protected static attribute $table - the name of table or elastic search index
    - Add custom parameters $customAttributes. By default only parameters Log::$attributes will be save.
    The keys of Log::$customAttributes are parameters identificators.
    The values of Log::$customAttributes are casts.
    Following casts are supported:
        - int
        - float 
        - string 
        - bool
        - array
    - You can add validation rules.
    - If you want to duplicate your logs to files you should override function toLogFileArray
0. Add the merkeleon_log.php config
`php artisan vendor:publish --provider="Merkeleon\Log\Providers\MerkeleonLogProvider"`
    - Point your log class
    - Point the driver (mysql or elastic)
    - If you want to duplicate your logs to files you should point the path to log file.
    
0. If you want save your logs to buffer and then bulk write all logs from buffer to storage
    - Add  buffer directory path to the merkeleon_log.php config. Buffer directory should be writable     
    - You should empty your buffer by calling command
     `php artisan merkeleon:log:bulk-insert-to-storage {logName}`

    
###Example:
          
    
    <?php
    
    namespace App\Models;
    
    use Merkeleon\Log\Model\Log;
    
    class AuditLog extends Log
    {
        protected static $table = 'audit_logs';
    
        protected static $customAttributes = [
            'user_id' => 'int',
            'user_id_related' => 'int',
            'data' => 'array',
        ];
    
        protected static $rules = [
            'event_type'      => 'required',
            'ip'              => 'required',
            'user_agent'      => 'required',
            'user_id'         => 'integer',
            'user_id_related' => 'integer',
        ];
    
        public function toLogFileArray()
        {
            return [
                "created_at"         => $this->created_at->format('Y-m-d H:i:s'),
                "ip"                 => $this->ip,
                "event_type"         => $this->event_type,
                "user_id"            => $this->user_id,
                "user_id_related"    => empty($this->user_id_related) ? '-' : $this->user_id_related,
                "user_agent"         => '"' . $this->user_agent . '"',
                "data"               => json_encode($this->data)
            ];
        }
    
    }
   
##    
    
        <?php
        
        return [
            'audit_log' => [
                'class' => \App\Models\AuditLog::class,
                'driver' => 'elastic',
                'log_file' => '/var/www/logs/audit_log.log'
            ],
        ];
  

## Usage

##Examples

    $auditLogRepository = LogRepository::make('audit_log');

    $auditLogRepository->write([
        'user_id'         => 1,
        'event_type'      => 'user_banned',
        'user_id_related' => 2,
        'data'            => [
            'user'         => [
                'id'   => '1',
                'name' => 'Admin User',
            ],
            'user_related' => [
                'id'   => '2',
                'name' => 'Test user'
            ]
        ]
    ]);

    $auditLogRepository->where('user_id', 1)->orderBy('event_type', 'asc')->paginate(10);
   
    $auditLogRepository->where('user_id', 1)->get();
    
    
##Examples
    
        $auditLogRepository = LogRepository::make('audit_log');
    
        $auditLogRepository->write([
            'user_id'         => 1,
            'event_type'      => 'user_banned',
            'user_id_related' => 2,
            'data'            => [
                'user'         => [
                    'id'   => '1',
                    'name' => 'Admin User',
                ],
                'user_related' => [
                    'id'   => '2',
                    'name' => 'Test user'
                ]
            ]
        ], true);
        
        $auditLogRepository->write([
            'user_id'         => 1,
            'event_type'      => 'user_login',
            'data'            => [
                'user'         => [
                    'id'   => '1',
                    'name' => 'Admin User',
                ]
            ]
        ], true);
    
        // call command `php artisan merkeleon:log:bulk-insert-to-storage audit_log`
        
        $auditLogRepository->where('user_id', 1)->orderBy('event_type', 'asc')->paginate(10);
       
        $auditLogRepository->where('user_id', 1)->get();
    
    
    
