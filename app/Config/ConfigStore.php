<?php

namespace App\Config;

use App\Enums\Filter\ConfigArea;
use App\Models\Filter\ConfigurationModel;
use App\Models\Filter\FilterSetting;
use App\Services\AuditLogger;
use BackedEnum;
use Closure;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * The only way to write row-based filter configuration (modules, lists, area rules, cooldowns,
 * GA permissions). Each write is audited and bumps its area's counter once per batch, all in one
 * transaction (contracts/config-write-api.md). Settings go through SettingsRepository instead.
 *
 * Not a singleton: one instance per batch.
 */
class ConfigStore
{
    /** @var array<string, ConfigArea> */
    private array $touched = [];

    private int $depth = 0;

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly ConfigVersion $version,
        private readonly ModuleRules $moduleRules,
    ) {}

    /**
     * @template TReturn
     *
     * @param  Closure(self): TReturn  $callback
     * @return TReturn
     */
    public function batch(Closure $callback): mixed
    {
        return $this->audit->transaction(function () use ($callback): mixed {
            $this->depth++;

            try {
                $result = $callback($this);
            } finally {
                $this->depth--;
            }

            if ($this->depth === 0) {
                $this->finish();
            }

            return $result;
        });
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @param  array<string, mixed>  $attributes
     */
    public function create(string $modelClass, array $attributes): Model
    {
        $area = $this->areaOf($modelClass);

        return $this->batch(function () use ($modelClass, $attributes, $area): Model {
            /** @var Model $model */
            $model = new $modelClass;
            $model->forceFill($attributes)->save();

            $this->audit->log($area->value.'.created', $model, [], $this->plain($model, array_keys($attributes)));
            $this->touched[$area->value] = $area;

            return $model;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Model $model, array $attributes): Model
    {
        $area = $this->areaOf($model::class);

        return $this->batch(function () use ($model, $attributes, $area): Model {
            $model->forceFill($attributes);
            $changed = array_keys($model->getDirty());

            if ($changed === []) {
                return $model;
            }

            $old = [];
            foreach ($changed as $field) {
                $old[$field] = $this->plainValue($model->getOriginal($field));
            }

            $model->save();

            $this->audit->log($area->value.'.updated', $model, $old, $this->plain($model, $changed));
            $this->touched[$area->value] = $area;

            return $model;
        });
    }

    public function delete(Model $model): void
    {
        $area = $this->areaOf($model::class);

        $this->batch(function () use ($model, $area): void {
            $old = $this->plain($model, array_keys($model->getAttributes()));
            $model->delete();

            $this->audit->log($area->value.'.deleted', $model, $old);
            $this->touched[$area->value] = $area;
        });
    }

    private function finish(): void
    {
        $touched = $this->touched;
        $this->touched = [];

        if (isset($touched[ConfigArea::Modules->value])) {
            $this->moduleRules->assertValid();
        }

        foreach ($touched as $area) {
            $this->version->bump($area);
        }
    }

    /**
     * @param  class-string  $modelClass
     */
    private function areaOf(string $modelClass): ConfigArea
    {
        if (! is_subclass_of($modelClass, ConfigurationModel::class) || $modelClass === FilterSetting::class) {
            throw new InvalidArgumentException("{$modelClass} is not written through ConfigStore.");
        }

        return $modelClass::configArea();
    }

    /**
     * @param  list<string>  $fields
     * @return array<string, mixed>
     */
    private function plain(Model $model, array $fields): array
    {
        $values = [];

        foreach ($fields as $field) {
            $values[$field] = $this->plainValue($model->getAttribute($field));
        }

        return $values;
    }

    private function plainValue(mixed $value): mixed
    {
        return $value instanceof BackedEnum ? $value->value : $value;
    }
}
