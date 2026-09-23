<?php

use App\Libraries\CompanyPaymentMethodService;
use App\Models\CompanyPaymentMethodModel;
use CodeIgniter\Test\CIUnitTestCase;

final class CompanyPaymentMethodTest extends CIUnitTestCase
{
    private array $tables = [];

    protected function setUp(): void
    {
        parent::setUp();
        helper('app');
        $db = db_connect('tests');
        foreach (['companies' => 'id', 'currencies' => 'id,company_id', 'branches' => 'id,company_id', 'sales_points_of_sale' => 'id,company_id',
            'company_payment_methods' => 'id,company_id,code,name,type,active,currency_ids,funds_destination,required_fields,allows_installments,requires_confirmation,branch_ids,point_of_sale_ids,percentage,created_at,updated_at,deleted_at'] as $table => $fields) {
            $columns = array_map(static fn($field) => '"' . $field . '" TEXT' . ($field === 'id' ? ' PRIMARY KEY' : ''), explode(',', $fields));
            $db->query('CREATE TABLE ' . $db->prefixTable($table) . ' (' . implode(',', $columns) . ')');
            $this->tables[] = $table;
        }
        foreach (['a', 'b'] as $company) {
            $db->table('companies')->insert(['id' => $company]);
            foreach (['currencies', 'branches', 'sales_points_of_sale'] as $table) {
                $db->table($table)->insert(['id' => $company, 'company_id' => $company]);
            }
        }
    }

    protected function tearDown(): void
    {
        foreach (array_reverse($this->tables) as $table) { db_connect('tests')->query('DROP TABLE ' . db_connect('tests')->prefixTable($table)); }
        parent::tearDown();
    }

    private function input(): array
    {
        return ['code' => 'transfer', 'name' => 'Transferencia', 'type' => 'transfer', 'active' => '1',
            'funds_destination' => 'bank', 'currency_ids' => ['a'], 'branch_ids' => ['a'], 'point_of_sale_ids' => ['a'],
            'required_fields' => ['referencia', 'entidad'], 'allows_installments' => '0', 'requires_confirmation' => '1'];
    }

    public function testCrudKeepsGuidAndLogicalHistory(): void
    {
        $service = new CompanyPaymentMethodService();
        $created = $service->save('a', $this->input());
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/i', $created['id']);
        $this->assertSame('TRANSFER', $created['code']);
        $this->assertSame(['a'], json_decode($created['currency_ids'], true));
        $updated = $service->save('a', array_replace($this->input(), ['name' => 'Banco', 'active' => '0']), $created['id']);
        $this->assertSame($created['id'], $updated['id']);
        $this->assertSame('Banco', $updated['name']);
        $this->assertEquals(0, $updated['active']);
        $service->remove('a', $created['id']);
        $this->assertNull((new CompanyPaymentMethodModel())->find($created['id']));
        $this->assertNotNull((new CompanyPaymentMethodModel())->withDeleted()->find($created['id'])['deleted_at']);
    }

    public function testDuplicateCodeIsRejected(): void
    {
        $service = new CompanyPaymentMethodService();
        $service->save('a', $this->input());
        $this->expectException(RuntimeException::class);
        $service->save('a', $this->input());
    }

    public function testCrossCompanyUpdateIsRejected(): void
    {
        $service = new CompanyPaymentMethodService();
        $method = $service->save('a', $this->input());
        $this->expectException(RuntimeException::class);
        $service->save('b', $this->input(), $method['id']);
    }

    public function testCrossCompanyDeleteIsRejected(): void
    {
        $service = new CompanyPaymentMethodService();
        $method = $service->save('a', $this->input());
        $this->expectException(RuntimeException::class);
        $service->remove('b', $method['id']);
    }

    public function testForeignSelectionsAreRejected(): void
    {
        foreach (['currency_ids', 'branch_ids', 'point_of_sale_ids'] as $field) {
            try {
                (new CompanyPaymentMethodService())->save('a', array_replace($this->input(), [$field => ['b']]));
                $this->fail('Foreign selection accepted');
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('empresa', $e->getMessage());
            }
        }
        $this->assertSame(0, (new CompanyPaymentMethodModel())->countAllResults());
    }

    public function testCurrencyIsRequired(): void
    {
        $this->expectException(RuntimeException::class);
        (new CompanyPaymentMethodService())->save('a', array_replace($this->input(), ['currency_ids' => []]));
    }

    public function testPercentagePersistsOnCreateAndUpdate(): void
    {
        $service = new CompanyPaymentMethodService();
        $method = $service->save('a', array_replace($this->input(), ['percentage' => '12.35']));
        $this->assertEquals(12.35, $method['percentage']);
        $method = $service->save('a', array_replace($this->input(), ['percentage' => '0.00']), $method['id']);
        $this->assertEquals(0, $method['percentage']);
        $method = $service->save('a', array_replace($this->input(), ['percentage' => '100.00']), $method['id']);
        $this->assertEquals(100, $method['percentage']);
    }

    public function testInvalidPercentageIsRejected(): void
    {
        foreach (['-1', '100.01', '1.234', 'abc', '', [], INF] as $value) {
            try {
                (new CompanyPaymentMethodService())->save('a', array_replace($this->input(), ['percentage' => $value]));
                $this->fail('Invalid percentage accepted');
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('porcentaje', $e->getMessage());
            }
        }
    }

    public function testCodeCanBeUsedByAnotherCompany(): void
    {
        $service = new CompanyPaymentMethodService();
        $first = $service->save('a', $this->input());
        $second = $service->save('b', array_replace($this->input(), ['currency_ids' => ['b'], 'branch_ids' => [], 'point_of_sale_ids' => []]));
        $this->assertNotSame($first['id'], $second['id']);
        $this->assertSame($first['code'], $second['code']);
    }

    public function testDeletedCodeCannotBeReused(): void
    {
        $service = new CompanyPaymentMethodService();
        $method = $service->save('a', $this->input());
        $service->remove('a', $method['id']);
        $this->expectException(RuntimeException::class);
        $service->save('a', $this->input());
    }

    public function testInvalidRulesAreRejected(): void
    {
        foreach (['type' => 'mixed', 'funds_destination' => 'invalid', 'active' => 'yes', 'required_fields' => ['invalid field']] as $key => $value) {
            try {
                (new CompanyPaymentMethodService())->save('a', array_replace($this->input(), [$key => $value]));
                $this->fail('Invalid rule accepted');
            } catch (RuntimeException $e) {
                $this->assertNotEmpty($e->getMessage());
            }
        }
        $this->assertSame(0, (new CompanyPaymentMethodModel())->countAllResults());
    }

    public function testCodesAllowSpacesOnCreateAndUpdateAndRemainUnique(): void
    {
        $service = new CompanyPaymentMethodService();
        $method = $service->save('a', array_replace($this->input(), ['code' => 'Tarjeta Debito']));
        $this->assertSame('TARJETA DEBITO', $method['code']);
        $method = $service->save('a', array_replace($this->input(), ['code' => '  Tarjeta Credito  ']), $method['id']);
        $this->assertSame('TARJETA CREDITO', $method['code']);
        $this->expectException(RuntimeException::class);
        $service->save('a', array_replace($this->input(), ['code' => 'tarjeta credito']));
    }
}
