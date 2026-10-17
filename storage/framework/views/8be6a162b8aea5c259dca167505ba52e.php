<?php ($panelUser = \Filament\Facades\Filament::auth()->user()); ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($panelUser): ?>
    <script>
        localStorage.setItem('filament:theme:mode', <?php echo e(\Illuminate\Support\Js::from($panelUser->theme)); ?>)

        window.addEventListener('theme-changed', (event) => {
            fetch(<?php echo e(\Illuminate\Support\Js::from(route('panel.preferences.theme'))); ?>, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': <?php echo e(\Illuminate\Support\Js::from(csrf_token())); ?>,
                },
                body: JSON.stringify({ theme: event.detail }),
            })
        })
    </script>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH /Users/mohammedelaraby/Work/Silkroad/Filter/VFilterWebsite/resources/views/filament/theme-sync.blade.php ENDPATH**/ ?>