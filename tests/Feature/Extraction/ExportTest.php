<?php

use App\Domain\Export\Writers;
use App\Domain\Extraction\JobRunner;
use App\Jobs\GenerateExport;
use App\Livewire\Extractions\Show;
use App\Models\Export;
use App\Models\ExtractionJob;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;
use Livewire\Livewire;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use Tests\Support\FakeWooStore;

beforeEach(function () {
    Carbon::setTestNow('2026-10-21 12:00:00');
    Sleep::fake(syncWithCarbon: true);
    Storage::fake('local');
    (new FakeWooStore)->install();
    $this->user = User::factory()->create();
    $this->job = ExtractionJob::create([
        'user_id' => $this->user->id, 'input_url' => FakeWooStore::ORIGIN.'/', 'status' => ExtractionJob::QUEUED,
        'options' => ['max_products' => 25, 'include_variations' => true], 'expires_at' => now()->addDays(7),
    ]);
    app(JobRunner::class)->run($this->job);
    $this->job->refresh();
    $this->tmp = tempnam(sys_get_temp_dir(), 'ss-export-');
});

afterEach(fn () => @unlink($this->tmp));

/** @return list<array<string, string>> */
function readCsv(string $path): array
{
    $content = file_get_contents($path);
    expect(str_starts_with($content, "\xEF\xBB\xBF"))->toBeTrue('CSV must start with a UTF-8 BOM');
    $lines = array_map(fn ($l) => str_getcsv($l, ',', '"', ''), preg_split('/\r\n(?=(?:[^"]*"[^"]*")*[^"]*$)/', rtrim(substr($content, 3), "\r\n")));
    $header = array_shift($lines);

    return array_map(fn ($row) => array_combine($header, $row), $lines);
}

it('writes one CSV row per product and per variation, Excel-ready', function () {
    $count = Writers::csv($this->job, $this->tmp);
    $rows = readCsv($this->tmp);
    $bySku = collect($rows)->keyBy('SKU');

    expect($count)->toBe(6)->and($rows)->toHaveCount(6)
        ->and(str_contains(file_get_contents($this->tmp), "\r\n"))->toBeTrue()
        ->and(array_column($rows, 'SKU'))->toBe(['HAT-WOOL', 'HAT-STRAW', 'CAP-VAR', 'CAP-RED', 'CAP-BLUE', 'HOOD-1']);

    expect($bySku['HAT-WOOL'])->toMatchArray([
        'Name' => 'Wool Beanie – Winter', 'Regular price' => '25.00', 'Sale price' => '19.99', 'On sale' => 'yes',
        'Currency' => 'EUR', 'Stock status' => 'instock', 'Stock quantity' => '3', 'Categories' => 'Hats', 'Brands' => 'Acme',
    ]);
    // no false sale prices
    expect($bySku['HAT-STRAW'])->toMatchArray(['Regular price' => '15.00', 'Sale price' => '', 'On sale' => 'no', 'Stock status' => 'outofstock']);
    // variations carry their own data plus a link to the parent
    expect($bySku['CAP-BLUE'])->toMatchArray([
        'Type' => 'variation', 'Parent SKU' => 'CAP-VAR', 'Parent ID' => '103', 'Variation' => 'Colour: Blue',
        'Regular price' => '14.00', 'Sale price' => '11.00', 'On sale' => 'yes', 'Currency' => 'EUR',
    ]);
    expect($bySku['CAP-VAR'])->toMatchArray(['Type' => 'variable', 'Regular price' => '', 'Min price' => '12.00', 'Max price' => '14.00', 'Attributes' => 'Colour: Red, Blue']);
    // descriptions are plain text by default
    expect($bySku['HAT-WOOL']['Description'])->toBe("About\nWarm & soft.\nx care");
});

it('neutralises spreadsheet formulas in text cells', function () {
    DB::table('products')->where('extraction_job_id', $this->job->id)->where('sku', 'HAT-STRAW')
        ->update(['name' => '=HYPERLINK("http://evil.example","click")', 'sku' => '+SUM(A1)']);

    Writers::csv($this->job, $this->tmp);
    $straw = collect(readCsv($this->tmp))->first(fn ($r) => str_contains($r['Name'], 'HYPERLINK'));

    expect($straw['Name'])->toStartWith("'=HYPERLINK")
        ->and($straw['SKU'])->toBe("'+SUM(A1)")
        ->and($straw['Regular price'])->toBe('15.00'); // numbers untouched
});

it('writes XLSX with numeric price cells', function () {
    $count = Writers::xlsx($this->job, $this->tmp.'.xlsx');

    $reader = new XlsxReader;
    $reader->open($this->tmp.'.xlsx');
    $rows = [];
    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $rows[] = $row->toArray();
        }
    }
    $reader->close();
    @unlink($this->tmp.'.xlsx');

    $header = $rows[0];
    $beanie = array_combine($header, $rows[1]);
    expect($count)->toBe(6)->and($rows)->toHaveCount(7)
        ->and($header[3])->toBe('SKU')
        ->and($beanie['Regular price'])->toBeNumeric()->toEqual(25)
        ->and($beanie['Sale price'])->toBe(19.99)
        ->and($beanie['Stock quantity'])->toBe(3)
        ->and($beanie['Name'])->toBe('Wool Beanie – Winter');
});

it('writes nested JSON', function () {
    $count = Writers::json($this->job, $this->tmp);
    $data = json_decode(file_get_contents($this->tmp), true, flags: JSON_THROW_ON_ERROR);

    expect($count)->toBe(4)
        ->and($data['meta']['store'])->toBe(FakeWooStore::ORIGIN)
        ->and($data['products'])->toHaveCount(4)
        ->and($data['products'][2]['sku'])->toBe('CAP-VAR')
        ->and($data['products'][2]['variations'][1])->toMatchArray(['sku' => 'CAP-BLUE', 'sale_price' => '11.00', 'variation_attributes' => ['Colour' => 'Blue']]);
});

it('generates an export from the job page and lets only the owner download it', function () {
    Queue::fake();
    Livewire::actingAs($this->user)->test(Show::class, ['job' => $this->job])->call('export', 'csv');

    $export = Export::sole();
    Queue::assertPushed(GenerateExport::class);
    (new GenerateExport($export->id))->handle();
    $export->refresh();

    expect($export->status)->toBe(Export::READY)->and($export->row_count)->toBe(6)
        ->and($export->filename)->toMatch('/^storesiphon-shop-acme-store-com-\d{8}-\d{6}\.csv$/');

    $this->actingAs($this->user)->get(route('exports.download', $export))
        ->assertOk()
        ->assertDownload($export->filename)
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    $this->actingAs(User::factory()->create())->get(route('exports.download', $export))->assertForbidden();
});

it('refuses expired export downloads', function () {
    $export = Export::create(['extraction_job_id' => $this->job->id, 'format' => 'csv', 'status' => Export::QUEUED]);
    (new GenerateExport($export->id))->handle();
    $export->refresh()->forceFill(['expires_at' => now()->subMinute()])->save();

    $this->actingAs($this->user)->get(route('exports.download', $export))->assertStatus(410);
});

it('does not offer exports before there are results', function () {
    $empty = ExtractionJob::create([
        'user_id' => $this->user->id, 'input_url' => FakeWooStore::ORIGIN.'/', 'status' => ExtractionJob::RUNNING,
        'options' => ['max_products' => 25],
    ]);

    Livewire::actingAs($this->user)->test(Show::class, ['job' => $empty])->call('export', 'csv')->assertForbidden();
    expect(Product::count())->toBe(6);
});
