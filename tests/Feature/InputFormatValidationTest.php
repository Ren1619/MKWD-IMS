<?php

use App\Http\Requests\ValidatedFormRequest;
use App\Models\InventoryItem;
use App\Models\User;
use App\Rules\InputFormat;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Validator;

class InputFormatTestRequest extends ValidatedFormRequest
{
    /** @var array<string, list<mixed>> */
    public array $testRules = [];

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return $this->testRules;
    }

    public function validation(): ValidatorContract
    {
        return $this->getValidatorInstance();
    }
}

/** @param array<string, mixed> $data
 * @param  array<string, list<mixed>>  $rules
 */
function formatRequestValidator(array $data, array $rules): ValidatorContract
{
    $request = InputFormatTestRequest::create('/', 'POST', $data);
    $request->testRules = $rules;
    $request->setContainer(app());

    return $request->validation();
}

test('all application request classes use the shared format boundary', function () {
    foreach (File::allFiles(app_path('Http/Requests')) as $file) {
        if ($file->getFilename() === 'ValidatedFormRequest.php') {
            continue;
        }
        $class = 'App\\Http\\Requests\\'.str_replace(['/', '.php'], ['\\', ''], $file->getRelativePathname());
        expect(is_subclass_of($class, ValidatedFormRequest::class))->toBeTrue($class);
    }
});

test('nested numeric fields reject malformed values without changing free text', function (string $value) {
    $validator = formatRequestValidator([
        'lines' => [['unit_cost' => $value]],
        'name' => 'José O’Connor-Santos, Jr.',
        'remarks' => "Repair / return: 1/2-inch fittings; approved by O'Connor & Co.\nKeep the original details.",
    ], [
        'lines.*.unit_cost' => ['required', 'numeric', 'min:0'],
        'name' => ['required', 'string', 'max:255'],
        'remarks' => ['required', 'string', 'max:3000'],
    ]);
    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('lines.0.unit_cost'))->toBeTrue()
        ->and($validator->errors()->has('name'))->toBeFalse()
        ->and($validator->errors()->has('remarks'))->toBeFalse();
})->with(['1e3', '1,000.00', 'NaN', 'Infinity', '12.3.4']);

test('valid typed inputs and optional blanks retain existing validation semantics', function () {
    $validator = formatRequestValidator([
        'quantity' => 12, 'amount' => '1234.50', 'optional_cost' => null,
        'stock_number' => 'MKWD-001/A.2', 'uacs_object_code' => '10404010-00',
        'property_number_prefix' => 'PPE_2026.01', 'date' => '2028-02-29',
        'email' => "o'connor+ims@example.com", 'password' => 'An unrestricted !@#$% password',
        'description' => "José's ½-inch pipe – returned / unused.",
    ], [
        'quantity' => ['required', 'integer', 'min:0'], 'amount' => ['required', 'numeric', 'min:0'],
        'optional_cost' => ['nullable', 'numeric'], 'stock_number' => ['string'],
        'uacs_object_code' => ['string'], 'property_number_prefix' => ['string'],
        'date' => ['required', 'date_format:Y-m-d'], 'email' => ['required', 'email'],
        'password' => ['required', 'string'], 'description' => ['required', 'string'],
    ]);
    expect($validator->passes())->toBeTrue($validator->errors()->toJson());
});

test('calendar validity bounds and foreign-key rules remain authoritative', function () {
    $validator = formatRequestValidator(['date' => '2026-02-31', 'quantity' => -1, 'warehouse_id' => 99999999], [
        'date' => ['required', 'date_format:Y-m-d'], 'quantity' => ['integer', 'min:0'],
        'warehouse_id' => ['integer', 'exists:warehouses,id'],
    ]);
    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->keys())->toContain('date', 'quantity', 'warehouse_id');
});

test('editing items enforces the same stock and UACS formats as creating items', function () {
    $item = InventoryItem::factory()->create();
    $original = $item->stock_number;
    $this->actingAs(User::factory()->inventoryManager()->create())
        ->put(route('inventory.items.update', $item), [
            'series_category_id' => $item->series_category_id, 'name' => $item->name,
            'stock_number' => 'INVALID STOCK NUMBER', 'uacs_object_code' => '123-45',
            'unit_of_measure' => $item->unit_of_measure, 'reorder_point' => 1, 'status' => 'active',
        ])->assertSessionHasErrors(['stock_number', 'uacs_object_code']);
    expect($item->fresh()->stock_number)->toBe($original);
});

test('frontend and Laravel agree on shared regex formats and show meaningful field errors', function () {
    $formats = [
        ['format' => 'integer', 'value' => '12'], ['format' => 'integer', 'value' => '1.2'],
        ['format' => 'integer', 'value' => '1e3'], ['format' => 'number', 'value' => '1234.50'],
        ['format' => 'number', 'value' => '1,234.50'], ['format' => 'stock_number', 'value' => 'MKWD-1/A.2'],
        ['format' => 'stock_number', 'value' => "MKWD\n"], ['format' => 'uacs_object_code', 'value' => '10404010-00'],
        ['format' => 'uacs_object_code', 'value' => '1040401000'], ['format' => 'property_number_prefix', 'value' => 'PPE_2026'],
        ['format' => 'property_number_prefix', 'value' => 'PPE 2026'], ['format' => 'date', 'value' => '2026-11-03'],
        ['format' => 'month', 'value' => '2026-11'], ['format' => 'datetime', 'value' => '2026-11-03T10:30'],
    ];
    $inputs = [
        ['type' => 'number', 'value' => '2.5'], ['type' => 'number', 'step' => '0.01', 'value' => '2.50'],
        ['type' => 'number', 'value' => '1e3'], ['type' => 'number', 'value' => '-1', 'min' => '0'],
        ['type' => 'number', 'value' => '11', 'max' => '10'], ['type' => 'text', 'value' => '', 'required' => true],
        ['type' => 'text', 'value' => '', 'required' => false], ['field' => 'uacs_object_code', 'value' => '123'],
        ['field' => 'lines[0][stock_number]', 'value' => 'MKWD-1/A.2'], ['type' => 'date', 'value' => '2026-02-31'],
        ['type' => 'month', 'value' => '2026-13'], ['type' => 'date', 'value' => '2028-02-29'],
        ['type' => 'email', 'value' => 'invalid', 'typeMismatch' => true],
        ['type' => 'url', 'value' => 'invalid', 'typeMismatch' => true],
        ['type' => 'text', 'value' => 'José O’Connor-Santos, Jr. / ½-inch'],
        ['type' => 'password', 'value' => 'Valid punctuation !@#$% & spaces'],
        ['type' => 'number', 'value' => '', 'badInput' => true],
        ['type' => 'checkbox', 'value' => '1', 'required' => true, 'valueMissing' => true],
        ['type' => 'date', 'value' => '', 'badInput' => true],
        ['type' => 'text', 'value' => '😀😀😀', 'maxLength' => 3],
    ];
    $result = Process::path(base_path())->input(json_encode(compact('formats', 'inputs'), JSON_THROW_ON_ERROR))
        ->timeout(60)->run(['node', base_path('tests/InputValidation.mjs')]);
    expect($result->successful())->toBeTrue($result->errorOutput());
    $frontend = json_decode($result->output(), true, flags: JSON_THROW_ON_ERROR);
    foreach ($formats as $position => $example) {
        $validator = Validator::make(['value' => $example['value']], ['value' => [new InputFormat($example['format'])]]);
        expect($frontend['matches'][$position])->toBe($validator->passes());
    }
    expect(array_map(fn (?string $message): bool => $message !== null, $frontend['errors']))
        ->toBe([true, false, true, true, true, true, false, true, false, true, true, false, true, true, false, false, true, true, true, false]);
    expect($frontend['errors'][18])->toContain('YYYY-MM-DD');
});

test('validation reveals errors in collapsed dialogs and focuses visible controls without an extra scroll', function () {
    $result = Process::path(base_path())->input('{"inputs":[],"formats":[]}')
        ->timeout(60)->run(['node', base_path('tests/InputValidation.mjs')]);
    expect($result->successful())->toBeTrue($result->errorOutput());
    $reveals = json_decode($result->output(), true, flags: JSON_THROW_ON_ERROR)['reveals'];
    $smooth = ['behavior' => 'smooth', 'block' => 'center', 'inline' => 'nearest'];
    expect($reveals['submission'])->toBe([
        ['action' => 'open'],
        ['action' => 'focus', 'options' => ['preventScroll' => true]],
        ['action' => 'scroll', 'options' => $smooth],
    ])->and($reveals['typing'])->toBe([
        ['action' => 'open'], ['action' => 'scroll', 'options' => $smooth],
    ])->and($reveals['picker'])->toBe([
        ['action' => 'open'],
        ['action' => 'focus-visible', 'options' => ['preventScroll' => true]],
        ['action' => 'scroll-visible', 'options' => $smooth],
    ])->and($reveals['reducedMotion'][1]['options']['behavior'])->toBe('auto')
        ->and($reveals['removed'])->toBe([]);
});

test('the validation summary displays every error and retains multiple server messages and error bags', function () {
    $result = Process::path(base_path())->input('{"inputs":[],"formats":[]}')
        ->timeout(60)->run(['node', base_path('tests/InputValidation.mjs')]);
    expect($result->successful())->toBeTrue($result->errorOutput());
    $rendered = json_decode($result->output(), true, flags: JSON_THROW_ON_ERROR);
    $html = new DOMDocument;
    @$html->loadHTML($rendered['summary']);
    $xpath = new DOMXPath($html);
    expect($xpath->query('//li')->length)->toBe(3)
        ->and($xpath->query('//button[@type="button"]')->length)->toBe(2)
        ->and($rendered['summary'])->toContain('3 errors need', 'Quantity', 'Date', 'Decision', 'Another person must approve this record.', 'aria-live="polite"')
        ->and($rendered['emptySummary'])->toBe('')
        ->and($rendered['productionSummary'])->toBe('')
        ->and($rendered['serverMessages'])->toBe([
            ['field' => 'quantity', 'message' => 'Enter a whole number.'],
            ['field' => 'quantity', 'message' => 'Quantity exceeds the issued balance.'],
            ['field' => 'lines.1.unit_cost', 'message' => 'Enter a valid cost.'],
            ['field' => 'createUser.email', 'message' => 'This email is already in use.'],
        ]);
});
