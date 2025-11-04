<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Services;

use ArrayAccess;
use Easy\Container\Attributes\Inject;
use Shared\Infrastructure\Helpers\AssetHelper;

class ModelRegistry implements ArrayAccess
{
    private ?array $registry = null;

    public function __construct(
        private AssetHelper $helper,

        #[Inject('config.dirs.root')]
        private string $root,
    ) {
        $this->populate();
    }

    public function toArray(): array
    {
        return $this->registry;
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->registry[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->registry[$offset];
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->registry[$offset] = $value;
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->registry[$offset]);
    }

    public function getRegistry(): array
    {
        return $this->registry;
    }

    public function save(): void
    {
        file_put_contents(
            $this->root . '/config/registry.json',
            json_encode($this->registry, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_LINE_TERMINATORS)
        );
    }

    private function populate(): void
    {
        if ($this->registry !== null) {
            return;
        }

        $this->registry = json_decode(
            file_get_contents($this->root . '/config/registry.base.json'),
            true
        ) ?? [];

        if (file_exists($this->root . '/config/registry.json')) {
            $registry = json_decode(
                file_get_contents($this->root . '/config/registry.json'),
                true
            ) ?? [];

            // Merge here
            $this->registry = $this->merge($this->registry, $registry);
        }
    }

    private function merge(array $base, array $override): array
    {
        if (!isset($override['directory'])) {
            return $base;
        }

        $map = [];
        foreach ($base['directory'] as $item) {
            $models = [];
            foreach ($item['models'] as $model) {
                $models[$model['key']] = $model;
            }

            $map[$item['key']] = $item;
            $map[$item['key']]['models'] = $models;
        }

        foreach ($override['directory'] as $item) {
            if (!isset($item['key'])) {
                continue;
            }

            $key = $item['key'];
            if (!isset($map[$key])) {
                if (isset($item['custom']) && $item['custom']) {
                    $models = [];
                    foreach ($item['models'] as $model) {
                        $models[$model['key']] = $model;
                    }

                    $map[$key] = $item;
                    $map[$key]['models'] = $models;
                }

                continue;
            }

            foreach ($item['models'] as $model) {
                if (!isset($map[$key]['models'][$model['key']])) {
                    if (isset($model['custom']) && $model['custom']) {
                        $map[$key]['models'][$model['key']] = $model;
                    }

                    continue;
                }

                // Override following properties:
                // - name
                // - description
                // - enabled
                if (isset($model['name'])) {
                    $map[$key]['models'][$model['key']]['name'] = $model['name'];
                }

                if (isset($model['description'])) {
                    $map[$key]['models'][$model['key']]['description'] = $model['description'];
                }

                if (isset($model['enabled'])) {
                    $map[$key]['models'][$model['key']]['enabled'] = $model['enabled'];
                }
            }
        }

        $map = array_values($map);
        foreach ($map as &$item) {
            $item['models'] = array_values($item['models']);
        }

        $base = $this->registry;
        $base['directory'] = $map;
        return $base;
    }
}
