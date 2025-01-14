<?php

namespace Genero\Sage\CacheTags;

use Genero\Sage\CacheTags\Contracts\Invalidator;
use Genero\Sage\CacheTags\Contracts\Store;
use Genero\Sage\CacheTags\Stores\WordpressDbStore;
use Genero\Sage\CacheTags\Console\DatabaseCommand;
use Genero\Sage\CacheTags\Console\FlushCommand;
use Genero\Sage\CacheTags\Contracts\Action;
use Roots\Acorn\ServiceProvider;

class CacheTagsServiceProvider extends ServiceProvider
{
    /**
     * @var CacheTags $cacheTags
     */
    protected $cacheTags;

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(CacheTags::class);

        $this->app->when(CacheTags::class)
            ->needs(Invalidator::class)
            ->give($this->app->config->get('cachetags.invalidator'));

        $this->app->when(CacheTags::class)
            ->needs(Store::class)
            ->give($this->app->config->get('cachetags.store', WordpressDbStore::class));

        $this->app->bind(Actions::class);
        $this->app->when(Actions::class)
            ->needs(Action::class)
            ->give($this->app->config->get('cachetags.action'));
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->cacheTags = $this->app->make(CacheTags::class);

        $this->bindActions();

        $this->publishes([
            __DIR__ . '/../config/cachetags.php' => $this->app->configPath('cachetags.php'),
        ], 'config');

        $this->commands([
            DatabaseCommand::class,
            FlushCommand::class,
        ]);
    }

    public function bindActions(): void
    {
        if (!$this->app->config->get('cachetags.disable', false)) {
            \add_action('wp_footer', [$this, 'saveCacheTags']);
            \add_action('shutdown', [$this, 'purgeCacheTags']);
        }

        // Bind all actions
        $this->app->make(Actions::class)->bind();
    }

    /**
     * Save the cache tags used on the rendered page.
     */
    public function saveCacheTags(): void
    {
        $this->cacheTags->save($this->currentUrl());
    }

    /**
     * At the end of the page load, purge any invalidated caches.
     */
    public function purgeCacheTags(): void
    {
        $this->cacheTags->purgeQueued();
    }

    /**
     * Return the current page url.
     */
    protected function currentUrl(): string
    {
        global $wp;
        return trailingslashit(
            \home_url($wp->request)
        );
    }
}
