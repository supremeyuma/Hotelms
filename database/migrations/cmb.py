import os
import re
from datetime import datetime

# --- Configuration ---
# Set the directory where migration files should be created
MIGRATION_DIR = 'database/migrations' 
# Custom delimiter to separate blocks of migration code in the input
BLOCK_DELIMITER = '--- MIGRATION_SEPARATOR ---'

# --- Standard Laravel Migration Stub ---
MIGRATION_STUB = r"""<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('##TABLE_NAME##', function (Blueprint $table) {
##SCHEMA_DEFINITION##
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('##TABLE_NAME##');
    }
};
"""

def process_single_migration_block(migration_name_snippet: str, up_function_code: str):
    """
    Creates a single new migration file, extracts the table name, and fills in 
    the custom schema definition into the 'up' function. (The core logic)

    :param migration_name_snippet: The base file name (e.g., 'create_properties_table.php').
    :param up_function_code: The body of the 'up' function, containing the Schema definition.
    """
    
    # 1. Generate the Timestamped Filename
    now = datetime.now()
    timestamp = now.strftime('%Y_%m_%d_%H%M%S')
    
    base_name = migration_name_snippet.replace('.php', '').strip()
    filename = f"{timestamp}_{base_name}.php"
    filepath = os.path.join(MIGRATION_DIR, filename)

    print(f"\nProcessing migration for: **{base_name}**")
    print(f"  -> File: {filepath}")
    
    # 2. Determine the Table Name
    # Regex to find the table name inside Schema::create('...')
    table_name_match = re.search(r"Schema::create\('([^']+)'", up_function_code)
    if not table_name_match:
        print("  ❌ Error: Could not deduce the table name from the provided 'up' function code.")
        return
        
    table_name = table_name_match.group(1)
    
    # 3. Extract the Inner Schema Definition
    # Find the content between the opening '{' and the closing '})' of the Schema::create call
    schema_definition_match = re.search(
        r"Schema::create\('"+ re.escape(table_name) + r"',\s*function\s*\(.*?\)\s*\{\s*(.*?)\s*\}\);", 
        up_function_code, 
        re.DOTALL
    )

    if not schema_definition_match:
        print("  ❌ Error: Could not extract the inner schema definition. Ensure the input contains a single 'Schema::create' call.")
        return
    
    inner_schema_lines = schema_definition_match.group(1).strip()
    
    # 4. Format the Schema Definition for Insertion
    # Indent the schema lines correctly for the stub (8 spaces + 4 spaces for Schema::create block)
    indented_schema = '\n'.join([
        '            ' + line.strip() for line in inner_schema_lines.split('\n')
    ])
    
    # 5. Populate the Migration Stub
    final_content = MIGRATION_STUB.replace('##TABLE_NAME##', table_name)
    final_content = final_content.replace('##SCHEMA_DEFINITION##', indented_schema)
    
    # 6. Write the File
    try:
        # Create the migrations directory if it doesn't exist
        os.makedirs(MIGRATION_DIR, exist_ok=True)
        
        with open(filepath, 'w') as f:
            f.write(final_content)
            
        print(f"  ✅ Success! Migration for '{table_name}' created.")
        
    except Exception as e:
        print(f"  ❌ An error occurred while writing the file: {e}")

# ----------------------------------------------------------------------

def process_batch_input(batch_input: str):
    """
    Splits the batch input string into individual blocks and processes each one.

    :param batch_input: A single string containing all migration blocks separated by the delimiter.
    """
    
    # Split the batch input into individual blocks
    blocks = batch_input.strip().split(BLOCK_DELIMITER)
    
    if not blocks:
        print("Input is empty.")
        return

    for block in blocks:
        # Clean up whitespace and skip empty blocks
        block = block.strip()
        if not block:
            continue
            
        # The block should start with the file name snippet on the first line, 
        # followed by the code block. We split the block at the first newline.
        parts = block.split('\n', 1)
        
        if len(parts) < 2:
            print(f"\nSkipping malformed block: \n{block[:50]}...")
            continue
            
        migration_name_snippet = parts[0].strip()
        up_function_code = parts[1].strip()
        
        # Process the extracted file name and code
        process_single_migration_block(migration_name_snippet, up_function_code)

# --- Example Usage ---
if __name__ == '__main__':
    
    # Example Input: Two separate migration definitions combined into one string
    batch_migration_input = """

    

create_room_types_table.php
public function up(): void
{
    Schema::create('room_types', function (Blueprint $table) {
        $table->id();
        $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();

        $table->string('title')->index()->comment('Room type name');
        $table->integer('max_occupancy')->default(1);
        $table->decimal('base_price', 10, 2)->index()->comment('Default price');
        $table->json('features')->nullable()->comment('JSON features: wifi, tv, etc');

        $table->timestamps();
        $table->softDeletes();
    });
}


--- MIGRATION_SEPARATOR ---
create_rooms_table.php
public function up(): void
{
    Schema::create('rooms', function (Blueprint $table) {
        $table->id();
        $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
        $table->foreignId('room_type_id')->constrained('room_types')->cascadeOnDelete();

        $table->string('room_number')->index()->comment('Unique room number');
        $table->string('status')->default('available')->index()->comment('available, occupied, maintenance');

        $table->json('meta')->nullable()->comment('Custom attributes per room');

        $table->timestamps();
        $table->softDeletes();
    });
}


--- MIGRATION_SEPARATOR ---
create_bookings_table.php

public function up(): void
{
    Schema::create('bookings', function (Blueprint $table) {
        $table->id();
        $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
        $table->foreignId('room_id')->constrained('rooms')->cascadeOnDelete();
        $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

        $table->string('booking_code')->unique()->index()->comment('Public booking reference');
        $table->date('check_in')->index();
        $table->date('check_out')->index();
        $table->integer('guests')->default(1);

        $table->decimal('total_amount', 12, 2)->index();
        $table->string('status')->default('pending')->index(); 

        $table->json('details')->nullable()->comment('Additional metadata');
        
        $table->timestamps();
        $table->softDeletes();
    });
}


--- MIGRATION_SEPARATOR ---

create_payments_table.php
public function up(): void
{
    Schema::create('payments', function (Blueprint $table) {
        $table->id();
        $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();

        $table->decimal('amount', 12, 2)->index()->comment('Amount paid');
        $table->string('method')->comment('Payment method: card, cash, transfer');
        $table->string('status')->default('pending')->index();
        $table->string('transaction_ref')->nullable()->index();

        $table->json('meta')->nullable()->comment('Gateway response or logs');

        $table->timestamps();
        $table->softDeletes();
    });
}



--- MIGRATION_SEPARATOR ---

create_orders_table.php
public function up(): void
{
    Schema::create('orders', function (Blueprint $table) {
        $table->id();
        $table->foreignId('booking_id')->nullable()->constrained('bookings')->nullOnDelete();
        $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

        $table->string('order_code')->unique()->index();
        $table->decimal('total', 12, 2)->default(0)->index();
        $table->string('status')->default('pending')->index();

        $table->timestamps();
        $table->softDeletes();
    });
}



--- MIGRATION_SEPARATOR ---



create_order_items_table.php
public function up(): void
{
    Schema::create('order_items', function (Blueprint $table) {
        $table->id();
        $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();

        $table->string('item_name')->comment('Name of item ordered');
        $table->integer('qty')->default(1);
        $table->decimal('price', 12, 2);

        $table->timestamps();
        $table->softDeletes();
    });
}



--- MIGRATION_SEPARATOR ---


create_order_events_table.php
public function up(): void
{
    Schema::create('order_events', function (Blueprint $table) {
        $table->id();
        $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
        $table->foreignId('staff_id')->nullable()->constrained('users')->nullOnDelete();

        $table->string('event')->index()->comment('Event type: created, updated, delivered');
        $table->json('meta')->nullable()->comment('Additional event details');

        $table->timestamps();
    });
}



--- MIGRATION_SEPARATOR ---

create_inventory_items_table.php
public function up(): void
{
    Schema::create('inventory_items', function (Blueprint $table) {
        $table->id();

        $table->string('name')->index();
        $table->string('sku')->unique()->index();
        $table->integer('quantity')->default(0);
        $table->string('unit')->nullable()->comment('kg, pcs, carton');
        $table->json('meta')->nullable();

        $table->timestamps();
        $table->softDeletes();
    });
}


--- MIGRATION_SEPARATOR ---

create_inventory_logs_table.php

public function up(): void
{
    Schema::create('inventory_logs', function (Blueprint $table) {
        $table->id();
        $table->foreignId('inventory_item_id')->constrained('inventory_items')->cascadeOnDelete();
        $table->foreignId('staff_id')->nullable()->constrained('users')->nullOnDelete();

        $table->integer('change')->comment('Positive or negative quantity');
        $table->string('type')->index()->comment('addition, removal, adjustment');
        $table->json('meta')->nullable();

        $table->timestamps();
    });
}


--- MIGRATION_SEPARATOR ---


create_maintenance_tickets_table.php

public function up(): void
{
    Schema::create('maintenance_tickets', function (Blueprint $table) {
        $table->id();
        $table->foreignId('room_id')->nullable()->constrained('rooms')->nullOnDelete();
        $table->foreignId('staff_id')->nullable()->constrained('users')->nullOnDelete();

        $table->string('title')->index();
        $table->text('description')->nullable();
        $table->string('status')->default('open')->index();
        $table->json('meta')->nullable();

        $table->timestamps();
        $table->softDeletes();
    });
}


--- MIGRATION_SEPARATOR ---



create_audit_logs_table.php

public function up(): void
{
    Schema::create('audit_logs', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

        $table->string('action')->index()->comment('Event type');
        $table->string('ip_address')->nullable()->index();
        $table->json('metadata')->nullable()->comment('Request details, old/new values');

        $table->timestamps();
    });
}


--- MIGRATION_SEPARATOR ---

create_settings_table.php

public function up(): void
{
    Schema::create('settings', function (Blueprint $table) {
        $table->id();
        $table->string('key')->unique()->index();
        $table->text('value')->nullable();
        $table->json('meta')->nullable();
        $table->timestamps();
    });
}


--- MIGRATION_SEPARATOR ---

create_images_table.php
public function up(): void
{
    Schema::create('images', function (Blueprint $table) {
        $table->id();

        $table->morphs('imageable'); // imageable_id + imageable_type

        $table->string('path')->comment('File path');
        $table->string('alt')->nullable()->comment('Alt text');
        $table->json('meta')->nullable()->comment('Dimension, size');

        $table->timestamps();
        $table->softDeletes();
    });
}



"""
    
    process_batch_input(batch_migration_input)