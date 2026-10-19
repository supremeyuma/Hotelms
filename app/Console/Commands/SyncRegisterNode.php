<?php

namespace App\Console\Commands;

use App\Models\Property;
use App\Models\Sync\SyncNode;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Operator path for issuing a node credential before any admin UI exists:
 * approve a node, then print its id and secret exactly once.
 *
 * The stored credential is the SHA-256 hash of the secret - the same
 * convention the sync.node middleware authenticates against - so re-running
 * this command can never recover a previously printed secret; it mints a new
 * node instead.
 */
class SyncRegisterNode extends Command
{
    protected $signature = 'sync:register
        {--name= : Display name for the node}
        {--type=terminal : Node type: lan_server or terminal}
        {--property= : Property id to bind this node to}';

    protected $description = 'Approve a sync node and print its node id and credential once';

    public function handle(): int
    {
        $name = trim((string) $this->option('name'));
        $type = trim((string) $this->option('type'));
        $propertyOption = trim((string) ($this->option('property') ?? ''));

        if ($name === '' || $propertyOption === '') {
            $this->error('Options --name and --property are required.');

            return self::FAILURE;
        }

        if (! in_array($type, ['lan_server', 'terminal'], true)) {
            $this->error('Option --type must be lan_server or terminal.');

            return self::FAILURE;
        }

        if (! ctype_digit($propertyOption)) {
            $this->error('Option --property must be a property id.');

            return self::FAILURE;
        }

        $property = Property::find((int) $propertyOption);

        if ($property === null) {
            $this->error("Property {$propertyOption} does not exist.");

            return self::FAILURE;
        }

        $secret = Str::random(64);

        $node = SyncNode::create([
            'node_id' => (string) Str::ulid(),
            'name' => $name,
            'type' => $type,
            'status' => 'active',
            'property_id' => $property->id,
            'credential' => hash('sha256', $secret),
        ]);

        $this->warn('The credential below is shown once and cannot be recovered.');
        $this->line('Node ID:    '.$node->node_id);
        $this->line('Credential: '.$secret);
        $this->line('Property:   '.$property->id.' ('.$property->name.')');
        $this->line('Type:       '.$type);

        return self::SUCCESS;
    }
}
