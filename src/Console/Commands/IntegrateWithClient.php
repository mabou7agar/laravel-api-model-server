<?php

namespace ApiServerPackage\Console\Commands;

use Illuminate\Console\Command;
use ApiServerPackage\Bridge\ApiPackageBridge;

class IntegrateWithClient extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'api-server:integrate-client
                            {--name=api-client : Name of the OAuth client to create}
                            {--scopes=* : OAuth scopes to assign to the client}
                            {--base-url= : Base URL of the API server}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate configuration for integrating with Laravel API Model Relations client';

    /**
     * The API package bridge instance.
     *
     * @var \ApiServerPackage\Bridge\ApiPackageBridge
     */
    protected $bridge;

    /**
     * Create a new command instance.
     *
     * @param  \ApiServerPackage\Bridge\ApiPackageBridge  $bridge
     * @return void
     */
    public function __construct(ApiPackageBridge $bridge)
    {
        parent::__construct();
        $this->bridge = $bridge;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Generating integration configuration for Laravel API Model Relations client...');

        // Get options
        $name = $this->option('name');
        $scopes = $this->option('scopes') ?: ['*'];
        $baseUrl = $this->option('base-url') ?: config('app.url');

        // Generate OAuth client
        $this->info('Creating OAuth client...');
        $client = $this->bridge->generateApiClient($name, $scopes);
        
        $this->info('OAuth client created successfully:');
        $this->table(
            ['Client ID', 'Client Secret'],
            [[$client['client_id'], $client['client_secret']]]
        );

        // Generate client configuration
        $config = $this->bridge->configureClientPackage($baseUrl, $client);
        
        $this->info('Client package configuration:');
        $this->line(json_encode($config, JSON_PRETTY_PRINT));
        
        // Generate example configuration file
        $configContent = "<?php\n\nreturn " . var_export($config, true) . ";\n";
        $configPath = storage_path('api_client_config.php');
        file_put_contents($configPath, $configContent);
        
        $this->info("Configuration file saved to: {$configPath}");
        $this->info('Copy this file to your client application\'s config directory.');
        
        // Show usage instructions
        $this->info("\nUsage instructions:");
        $this->line("1. Copy the configuration file to your client application's config directory");
        $this->line("2. In your client application, update the 'api_model.php' config file with these settings");
        $this->line("3. Test the connection using the provided client credentials");
        
        return 0;
    }
}
