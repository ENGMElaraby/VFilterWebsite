@php($panelUser = \Filament\Facades\Filament::auth()->user())
@if ($panelUser)
    <script>
        localStorage.setItem('filament:theme:mode', {{ \Illuminate\Support\Js::from($panelUser->theme) }})

        window.addEventListener('theme-changed', (event) => {
            fetch({{ \Illuminate\Support\Js::from(route('panel.preferences.theme')) }}, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': {{ \Illuminate\Support\Js::from(csrf_token()) }},
                },
                body: JSON.stringify({ theme: event.detail }),
            })
        })
    </script>
@endif
