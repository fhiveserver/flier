# Flier — Guia Completo

Este guia cobre todos os recursos do Flier. Se você quer uma visão geral rápida, comece pelo [README](../README.md).

---

## Índice

- [Instalação e configuração](#instalação-e-configuração)
- [ResourceBuilder](#resourcebuilder)
  - [Criando um recurso](#criando-um-recurso)
  - [Editando um recurso existente](#editando-um-recurso-existente)
  - [Usando um driver](#usando-um-driver)
  - [Nomes de método reservados](#nomes-de-método-reservados)
  - [Todos os métodos do ResourceBuilder](#todos-os-métodos-do-resourcebuilder)
- [PropertyProxy](#propertyproxy)
- [Operações](#operações)
- [FHIRPath Patch](#fhirpath-patch)
- [SearchBuilder](#searchbuilder)
  - [Construindo uma URL](#construindo-uma-url)
  - [Executando uma busca](#executando-uma-busca)
  - [Modificadores](#modificadores)
  - [Todos os métodos do SearchBuilder](#todos-os-métodos-do-searchbuilder)
- [Drivers](#drivers)
  - [ArrayResourceDriver](#arrayresourcedriver)
  - [FHIRHttpDriver](#fhirhttpdriver)
  - [Escrevendo seu próprio driver](#escrevendo-seu-próprio-driver)
- [Parseando respostas FHIR](#parseando-respostas-fhir)
- [Macros](#macros)
- [Fontes de parâmetros de busca](#fontes-de-parâmetros-de-busca)
  - [InMemorySearchParameterSource](#inmemorysearchparametersource)
  - [CompositeSearchParameterSource](#compositesearchparametersource)
  - [Escrevendo sua própria fonte](#escrevendo-sua-própria-fonte)

---

## Instalação e configuração

```bash
composer require fhive/flier
```

O `FlierServiceProvider` é descoberto automaticamente pelo Laravel. Nenhuma configuração manual necessária.

---

## ResourceBuilder

O `ResourceBuilder` é o núcleo do Flier. Ele permite construir e editar recursos FHIR com uma API PHP fluente. Todo nome de propriedade FHIR vira um método via `__call`. As operações são gravadas como um log imutável e aplicadas somente quando você pede um resultado.

### Criando um recurso

Comece com `Flier::resource($type)` e encadeie os setters de propriedade:

```php
use FHIR\Flier\Flier;

$patient = Flier::resource('Patient')
    ->name([['family' => 'Costa', 'given' => ['Mariana']]])
    ->birthDate('1992-08-10')
    ->gender('female')
    ->active(true)
    ->toArray();
```

`toArray()` aplica todas as operações acumuladas e retorna o array final. Nada é enviado a lugar nenhum.

Você pode usar qualquer nome de propriedade FHIR válido — o Flier não sabe nem se importa com o que são. `birthDate`, `status`, `valueQuantity`, `component` — todos funcionam da mesma forma. Isso significa que recursos customizados funcionam exatamente igual aos recursos padrão do FHIR.

### Editando um recurso existente

Passe um array de recurso existente como segundo argumento:

```php
$existing = [
    'resourceType' => 'Patient',
    'id' => 'p1',
    'gender' => 'male',
    'birthDate' => '1990-01-01',
];

$updated = Flier::resource('Patient', $existing)
    ->birthDate('1990-03-15')  // substitui via AddOperation
    ->active(true)             // adiciona nova propriedade
    ->toArray();
```

> **Atenção:** chamar `->birthDate('1990-03-15')` em um recurso que já tem `birthDate` grava uma `AddOperation`, que **substitui** o valor existente. Se você quiser ser explícito sobre a intenção, use `->birthDate()->replace('1990-03-15')` — isso gera uma operação `replace` no documento FHIRPath Patch.

### Usando um driver

Por padrão, todas as operações ficam em memória. Para enviá-las a algum lugar, defina um driver com `useDriver()`:

```php
$driver = new FHIRHttpDriver('https://hapi.fhir.org/baseR4');

Flier::resource('Patient')
    ->name([['family' => 'Doe']])
    ->gender('female')
    ->useDriver($driver)
    ->create(); // POST /Patient
```

O driver também pode ser passado como string de classe e será resolvido pelo container:

```php
->useDriver(MyCustomDriver::class)
```

### Nomes de método reservados

Os seguintes nomes de método são reservados — são métodos reais no `ResourceBuilder` e não serão interceptados como setters de propriedade FHIR:

```
create   update   put   delete   index
useDriver   toArray   asFHIRPatch   addOperation
getData   getOperations   getResourceType
```

Se um recurso FHIR tiver uma propriedade com um desses nomes (improvável, mas possível com recursos customizados), adicione o sufixo `Property` ao nome do método:

```php
// Define a propriedade FHIR "delete" em um recurso customizado
$builder->deleteProperty('algum-valor');

// Define a propriedade FHIR "create"
$builder->createProperty('algum-modo');
```

A mesma convenção se aplica ao `SearchBuilder` — `->searchProperty('valor')` define o parâmetro de busca FHIR `search`.

### Todos os métodos do ResourceBuilder

| Método | Retorna | Descrição |
|---|---|---|
| `->algumaPropriedade($valor)` | `static` | Grava um `AddOperation` para `algumaPropriedade` |
| `->algumaPropriedade()` | `PropertyProxy` | Retorna um proxy para edições cirúrgicas (veja abaixo) |
| `->useDriver($driver)` | `static` | Define o driver de operações |
| `->create()` | `mixed` | Semântica POST — aplica ops ou delega ao driver |
| `->update()` | `mixed` | Semântica PATCH — aplica ops ou envia FHIRPath Patch |
| `->put()` | `mixed` | Semântica PUT — substituição completa ou delega ao driver |
| `->delete()` | `mixed` | Semântica DELETE — array vazio ou delega ao driver |
| `->toArray()` | `array` | Aplica todas as operações e retorna o resultado |
| `->asFHIRPatch()` | `array` | Gera um recurso `Parameters` para FHIRPath Patch |
| `->addOperation($op)` | `static` | Adiciona uma operação pré-construída (usado pelo `PropertyProxy`) |
| `->getData()` | `array` | Retorna os dados brutos atuais (antes das operações) |
| `->getOperations()` | `list<Operation>` | Retorna a lista de operações pendentes |
| `->getResourceType()` | `string` | Retorna o tipo do recurso (ex.: `"Patient"`) |

---

## PropertyProxy

Chamar um método de propriedade **sem argumentos** retorna um `PropertyProxy` — um pequeno cursor imutável que dá controle sobre qual operação será gravada.

```php
$builder = Flier::resource('Patient', $existing);

// Deletar uma propriedade
$builder->deceased()->delete();

// Substituir o valor de uma propriedade
$builder->status()->replace('active');

// Ler o valor atual sem gravar nenhuma operação
$nomeAtual = $builder->name()->value();

// Converter para string
echo $builder->birthDate(); // chama __toString() no proxy
```

`delete()` e `replace()` retornam o `ResourceBuilder` pai, então você pode continuar encadeando:

```php
Flier::resource('Patient', $existing)
    ->deceased()->delete()
    ->status()->replace('active')
    ->gender()->replace('female')
    ->toArray();
```

---

## Operações

O Flier grava mutações como objetos de operação. Elas são aplicadas em ordem quando você chama `toArray()`, `create()`, `update()` ou `put()`.

| Operação | Criada por | Aplica como | Tipo no FHIRPath Patch |
|---|---|---|---|
| `AddOperation` | `->prop($valor)` | Define `data['prop'] = $valor` | `add` |
| `ReplaceOperation` | `->prop()->replace($valor)` | Define `data['prop'] = $valor` | `replace` |
| `DeleteOperation` | `->prop()->delete()` | Remove `data['prop']` | `delete` |

Você também pode adicionar operações diretamente se estiver construindo ferramentas em cima do Flier:

```php
use FHIR\Flier\Builder\Operations\AddOperation;
use FHIR\Flier\Builder\Operations\DeleteOperation;
use FHIR\Flier\Builder\Operations\ReplaceOperation;

$builder->addOperation(new AddOperation('status', 'active'));
$builder->addOperation(new ReplaceOperation('gender', 'female'));
$builder->addOperation(new DeleteOperation('deceased'));
```

Para inspecionar o que está pendente sem aplicar nada:

```php
$ops = $builder->getOperations(); // list<Operation>
```

---

## FHIRPath Patch

Quando você chama `update()` com um driver HTTP, o Flier gera um documento [FHIRPath Patch](https://hl7.org/fhir/http.html#patch) a partir do log de operações e o envia como uma requisição `PATCH`. Você também pode gerar o documento manualmente:

```php
$patch = Flier::resource('Patient', $existing)
    ->status()->replace('inactive')
    ->birthDate()->delete()
    ->name([['family' => 'Novo']])
    ->asFHIRPatch();
```

O resultado é um recurso FHIR `Parameters`:

```php
[
    'resourceType' => 'Parameters',
    'parameter' => [
        [
            'name' => 'operation',
            'part' => [
                ['name' => 'type',  'valueCode'   => 'replace'],
                ['name' => 'path',  'valueString' => 'Patient.status'],
                ['name' => 'value', 'valueString' => 'inactive'],
            ],
        ],
        [
            'name' => 'operation',
            'part' => [
                ['name' => 'type', 'valueCode'   => 'delete'],
                ['name' => 'path', 'valueString' => 'Patient.birthDate'],
            ],
        ],
        [
            'name' => 'operation',
            'part' => [
                ['name' => 'type',  'valueCode'   => 'add'],
                ['name' => 'path',  'valueString' => 'Patient'],
                ['name' => 'name',  'valueString' => 'name'],
                ['name' => 'value', 'valueArray'  => [['family' => 'Novo']]],
            ],
        ],
    ],
]
```

Isso é exatamente o que `FHIRHttpDriver::update()` envia pela rede.

---

## SearchBuilder

O `SearchBuilder` acumula parâmetros de busca FHIR e constrói uma URL ou delega a um driver de busca. Assim como o `ResourceBuilder`, todo nome de parâmetro é um método mágico.

### Construindo uma URL

```php
$url = Flier::search('Patient')
    ->family('Silva')
    ->birthdate('ge1990-01-01')
    ->gender('male')
    ->asUrl('https://hapi.fhir.org/baseR4');

// → "https://hapi.fhir.org/baseR4/Patient?family=Silva&birthdate=ge1990-01-01&gender=male"
```

Sem uma URL base, `asUrl()` retorna apenas o caminho:

```php
Flier::search('Observation')->code('8867-4')->asUrl();
// → "Observation?code=8867-4"
```

### Executando uma busca

Com `useDriver()`, `search()` delega ao driver e retorna o que ele responder:

```php
// Contra um servidor externo — retorna o array Bundle decodificado
$bundle = Flier::search('Patient')
    ->family('Silva')
    ->useDriver(new FHIRHttpDriver('https://hapi.fhir.org/baseR4'))
    ->search();

// Contra um índice local (fhive/flier-storage) — retorna Collection<int, Resource>
$resources = Flier::search('Patient')
    ->family('Silva')
    ->useDriver(app(\FHIR\Flier\Drivers\SearchDriver::class))
    ->search();
```

Sem driver, `search()` retorna o mesmo que `asUrl()` — apenas a query string.

### Modificadores

O FHIR Search suporta modificadores como `:exact`, `:contains`, `:text`, `:not`. Passe-os como segundo argumento:

```php
Flier::search('Patient')
    ->name('Silva', ':exact')       // name:exact=Silva
    ->identifier('12345', ':text')  // identifier:text=12345
    ->gender('male', ':not')        // gender:not=male
```

### Todos os métodos do SearchBuilder

| Método | Retorna | Descrição |
|---|---|---|
| `->algumaParam($valor, $modificador?)` | `static` | Adiciona um parâmetro de busca |
| `->useDriver($driver)` | `static` | Define o driver de busca |
| `->search()` | `mixed` | Executa — string URL sem driver, resultado do driver com driver |
| `->asUrl($baseUrl?)` | `string` | Gera a query string com URL base opcional |
| `->getParams()` | `list<SearchParam>` | Retorna os parâmetros acumulados |
| `->getResourceType()` | `string` | Retorna o tipo do recurso |

---

## Drivers

Drivers são o mecanismo pelo qual o Flier se comunica com o mundo externo. Um `ResourceDriver` lida com create, update, put e delete. Um `SearchDriver` lida com busca. O `FHIRHttpDriver` implementa os dois.

### ArrayResourceDriver

O driver padrão. Aplica operações em memória e retorna o array resultante. Sem rede, sem banco de dados, sem dependências.

```php
use FHIR\Flier\Drivers\ArrayResourceDriver;

$driver = new ArrayResourceDriver;

// Nível baixo: aplica operações diretamente
$result = $driver->apply($data, $operations);
```

Normalmente você não precisa instanciar isso — é o que `toArray()` usa internamente.

### FHIRHttpDriver

Envia operações a um servidor FHIR externo usando a facade `Http` do Laravel. Trata toda a semântica HTTP FHIR corretamente:

- `create()` → `POST /{resourceType}` com o corpo completo do recurso
- `update()` → `PATCH /{resourceType}/{id}` com um recurso Parameters de FHIRPath Patch
- `put()` → `PUT /{resourceType}/{id}` com o corpo completo do recurso
- `delete()` → `DELETE /{resourceType}/{id}`
- `search()` → `GET /{resourceType}?...` com parâmetros de query

Todas as requisições usam `application/fhir+json` tanto para `Accept` quanto para `Content-Type`.

```php
use FHIR\Flier\Drivers\FHIRHttpDriver;

$driver = new FHIRHttpDriver('https://hapi.fhir.org/baseR4');

// Criar
Flier::resource('Patient')
    ->name([['family' => 'Doe']])
    ->gender('female')
    ->useDriver($driver)
    ->create();

// Atualizar com FHIRPath Patch
Flier::resource('Patient', ['id' => 'p1', ...])
    ->status()->replace('inactive')
    ->useDriver($driver)
    ->update();

// PUT — substituição completa
Flier::resource('Patient', $recursoCompleto)
    ->useDriver($driver)
    ->put();

// DELETE
Flier::resource('Patient', ['id' => 'p1'])
    ->useDriver($driver)
    ->delete();

// Search — retorna o array Bundle decodificado
$bundle = Flier::search('Patient')
    ->family('Doe')
    ->useDriver($driver)
    ->search();
```

### Escrevendo seu próprio driver

Implemente `ResourceDriver`, `SearchDriver` ou ambos:

```php
use FHIR\Flier\Builder\Operations\Operation;
use FHIR\Flier\Builder\SearchParam;
use FHIR\Flier\Drivers\ResourceDriver;
use FHIR\Flier\Drivers\SearchDriver;

class DriverComAuditoria implements ResourceDriver, SearchDriver
{
    public function __construct(
        private readonly ResourceDriver $inner,
        private readonly AuditService $audit,
    ) {}

    public function create(string $resourceType, array $data, array $operations): array
    {
        $result = $this->inner->create($resourceType, $data, $operations);
        $this->audit->registrar('create', $resourceType, $result['id'] ?? null);

        return $result;
    }

    public function update(string $resourceType, array $data, array $operations): array
    {
        $result = $this->inner->update($resourceType, $data, $operations);
        $this->audit->registrar('update', $resourceType, $data['id'] ?? null);

        return $result;
    }

    public function put(string $resourceType, array $data, array $operations): array
    {
        return $this->inner->put($resourceType, $data, $operations);
    }

    public function delete(string $resourceType, array $data): array
    {
        $this->audit->registrar('delete', $resourceType, $data['id'] ?? null);

        return $this->inner->delete($resourceType, $data);
    }

    /** @param list<SearchParam> $params */
    public function search(string $resourceType, array $params): mixed
    {
        return $this->inner->search($resourceType, $params);
    }
}
```

Depois use assim:

```php
$driver = new DriverComAuditoria(
    new FHIRHttpDriver('https://hapi.fhir.org/baseR4'),
    app(AuditService::class),
);

Flier::resource('Patient', $data)
    ->status()->replace('inactive')
    ->useDriver($driver)
    ->update();
```

---

## Parseando respostas FHIR

Quando você recebe uma resposta de um servidor FHIR, pode encapsulá-la em um builder para edição posterior:

```php
// Recurso único
$response = Http::get('https://hapi.fhir.org/baseR4/Patient/p1')->json();

$builder = Flier::from($response);
echo $builder->getResourceType(); // "Patient"
echo $builder->name()->value();   // o array name
```

`Flier::from()` lança `InvalidArgumentException` se o array não tiver a chave `resourceType`.

### Parseando um Bundle

```php
$bundle = Http::get('https://hapi.fhir.org/baseR4/Patient?family=Silva')->json();

// Retorna uma Collection<int, ResourceBuilder>
$builders = Flier::fromBundle($bundle);

// Trabalhar com tipos específicos
$patients = $builders->filter(fn ($b) => $b->getResourceType() === 'Patient');

// Extrair dados
$nomes = $patients->map(fn ($b) => $b->name()->value());

// Editar em lote — cada builder é independente
$patches = $patients->map(fn ($b) =>
    $b->status()->replace('active')->asFHIRPatch()
);
```

Entradas no Bundle sem chave `resource` são ignoradas silenciosamente.

---

## Macros

Tanto `ResourceBuilder` quanto `SearchBuilder` usam `Illuminate\Support\Traits\Macroable`. Isso significa que qualquer código pode registrar novos métodos neles no momento do boot — sem subclasses, sem modificar o pacote.

É assim que o Flier permanece pequeno enquanto suporta operações específicas de domínio (lookups de terminologia, execução de SQL on FHIR, etc.) por meio de módulos separados.

### Registrando uma macro

```php
use FHIR\Flier\Builder\ResourceBuilder;

// Em um ServiceProvider::boot()
ResourceBuilder::macro('resumir', function (): string {
    /** @var ResourceBuilder $this */
    return "{$this->getResourceType()} — {$this->name()->value()[0]['family']} " .
           "({$this->birthDate()->value()})";
});

// Uso
echo Flier::resource('Patient', $patient)->resumir();
// → "Patient — Silva (1990-03-15)"
```

O docblock `/** @var ResourceBuilder $this */` diz à sua IDE o que é `$this` dentro do closure.

### Protegendo o registro

Se seu módulo é opcional (pode não estar sempre instalado junto com o Flier), proteja o registro:

```php
private function registrarMacrosFlier(): void
{
    if (! class_exists(ResourceBuilder::class)) {
        return;
    }

    ResourceBuilder::macro('meuMetodo', function (): mixed {
        // ...
    });
}
```

### Prioridade das macros

Macros têm prioridade sobre o magic de propriedades FHIR. Se você registrar uma macro chamada `status`, chamar `->status()` sempre invocará a macro — não o handler `__call`. Tenha isso em mente ao nomear macros.

### Macros nativas dos módulos Fhive

| Módulo | Macro | Disponível em | Descrição |
|---|---|---|---|
| `fhive/terminology` | `lookup()` | `ResourceBuilder` | Inicia uma operação `$lookup` em um CodeSystem |
| `fhive/terminology` | `expand()` | `ResourceBuilder` | Expande um ValueSet |
| `fhive/terminology` | `validateCode($code)` | `ResourceBuilder` | Valida um código |
| `fhive/sql-on-fhir` | `run($recursos)` | `ResourceBuilder` | Executa uma ViewDefinition em memória |

---

## Fontes de parâmetros de busca

O Flier inclui um sistema de registro de fontes que permite que módulos declarem quais definições de `SearchParameter` eles gerenciam. Isso é usado pelo `fhive/flier-storage` ao indexar recursos — mas a API de fontes em si é open-source e disponível para qualquer um.

### O contrato

```php
namespace FHIR\Flier\Contracts;

interface FHIRSearchParameterSource
{
    /** @return list<FHIRSearchParameter> */
    public function forType(string $resourceType): array;
}
```

`FHIRSearchParameter` é um objeto de valor com `code`, `type` e `expression` (uma expressão FHIRPath).

### Registrando uma fonte

Chame `Flier::registerSearchParameterSource()` no método `boot()` de qualquer módulo. É seguro chamar múltiplas vezes de múltiplos módulos.

```php
use FHIR\Flier\Flier;
use FHIR\Flier\Sources\InMemorySearchParameterSource;

public function boot(): void
{
    Flier::registerSearchParameterSource(
        InMemorySearchParameterSource::only('LeitoHospitalar', [
            new SearchParam(code: 'ala',   type: 'token',  expression: 'LeitoHospitalar.ala'),
            new SearchParam(code: 'andar', type: 'string', expression: 'LeitoHospitalar.andar'),
            new SearchParam(code: 'data',  type: 'date',   expression: 'LeitoHospitalar.data'),
        ])
    );
}
```

### InMemorySearchParameterSource

A fonte mais simples — mantém parâmetros definidos diretamente em código. Ótima para testes e para tipos de recursos embutidos:

```php
use FHIR\Flier\Sources\InMemorySearchParameterSource;

// Tipo único (mais comum)
$source = InMemorySearchParameterSource::only('Patient', [
    new SearchParam(code: 'family', type: 'string', expression: 'Patient.name.family'),
    new SearchParam(code: 'gender', type: 'token',  expression: 'Patient.gender'),
]);

// Múltiplos tipos de uma vez
$source = new InMemorySearchParameterSource([
    'Patient'     => [...],
    'Observation' => [...],
]);
```

### CompositeSearchParameterSource

O `CompositeSearchParameterSource` agrega múltiplas fontes. O Flier o registra como singleton e faz o bind de `FHIRSearchParameterSource` para ele. Toda chamada a `Flier::registerSearchParameterSource()` adiciona ao composite.

Quando duas fontes definem o mesmo `code` para o mesmo tipo de recurso, **a primeira registrada vence**. Registre fontes de maior prioridade antes no boot.

```php
// Em AppServiceProvider::boot() — roda antes dos providers de módulos
Flier::registerSearchParameterSource($overridesHardCoded); // vence nos conflitos

// Em um ServiceProvider de módulo::boot()
Flier::registerSearchParameterSource($fonteModulo); // menor prioridade
```

### Escrevendo sua própria fonte

Se seus parâmetros de busca estão em um banco de dados, arquivo ou serviço remoto, implemente `FHIRSearchParameterSource`:

```php
use FHIR\Flier\Contracts\FHIRSearchParameter;
use FHIR\Flier\Contracts\FHIRSearchParameterSource;

class FonteDeParametrosBancoDeDados implements FHIRSearchParameterSource
{
    /** @return list<FHIRSearchParameter> */
    public function forType(string $resourceType): array
    {
        return SearchParameterModel::query()
            ->where('resource_type', $resourceType)
            ->get()
            ->map(fn ($row) => new SearchParam(
                code: $row->code,
                type: $row->type,
                expression: $row->expression,
            ))
            ->all();
    }
}
```

Depois registre:

```php
Flier::registerSearchParameterSource(app(FonteDeParametrosBancoDeDados::class));
```

> **Nota FrankenPHP / Octane:** Fontes são registradas durante o `boot()`, antes que qualquer requisição seja servida. O singleton composite é efetivamente imutável em runtime. Isso é intencional e seguro — `add()` nunca deve ser chamado durante uma requisição.
