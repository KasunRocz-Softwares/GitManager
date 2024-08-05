<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Projects') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">

                    <!-- Project creation form -->
                    <form id="project-form" class="mt-6 space-y-6">
                        @csrf
                        <div>
                            <x-input-label for="name" :value="__('Project Name')" />
                            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" required autofocus />
                        </div>

                        <div>
                            <x-input-label for="username" :value="__('Username')" />
                            <x-text-input id="username" name="username" type="text" class="mt-1 block w-full" required />
                        </div>

                        <div>
                            <x-input-label for="password" :value="__('Password')" />
                            <x-text-input id="password" name="password" type="password" class="mt-1 block w-full" required />
                        </div>

                        <div>
                            <x-input-label for="host" :value="__('Host IP Address')" />
                            <x-text-input id="host" name="host" type="text" class="mt-1 block w-full" required />
                        </div>

                        <div class="flex items-center gap-4">
                            <x-primary-button type="button" onclick="submitForm()">{{ __('Save') }}</x-primary-button>
                        </div>
                    </form>

                    <!-- Existing projects list -->
                    <div class="mt-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                            {{ __('Existing Projects') }}
                        </h3>

                        <ul id="projects-list" class="mt-4 space-y-2">
                            <!-- Projects will be loaded here by JavaScript -->
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            fetchProjects();

            // Handle form submission
            window.submitForm = async () => {
                const form = document.getElementById('project-form');
                const formData = new FormData(form);

                try {
                    const response = await fetch('{{ route('projects.store') }}', {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-CSRF-TOKEN': formData.get('_token'),
                            'Accept': 'application/json',
                        }
                    });

                    const result = await response.json();

                    if (response.ok) {
                        // Handle success
                        document.getElementById('projects-list').innerHTML += `
                            <li class="border p-4 rounded">
                                <h4 class="font-semibold text-gray-800 dark:text-gray-200">${result.name}</h4>
                                <p class="text-gray-600 dark:text-gray-400">${result.host}</p>
                            </li>
                        `;
                        form.reset();
                    } else {
                        // Handle validation errors
                        Object.entries(result.errors || {}).forEach(([field, messages]) => {
                            document.getElementById(`${field}-error`).textContent = messages.join(', ');
                        });
                    }
                } catch (error) {
                    console.error('Error:', error);
                }
            };
        });

        async function fetchProjects() {
            try {
                const response = await fetch('{{ url('/api/projects') }}');
                const projects = await response.json();

                const list = document.getElementById('projects-list');
                list.innerHTML = projects.map(project => `
                    <li class="border p-4 rounded">
                        <h4 class="font-semibold text-gray-800 dark:text-gray-200">${project.name}</h4>
                        <p class="text-gray-600 dark:text-gray-400">${project.host}</p>
                    </li>
                `).join('');
            } catch (error) {
                console.error('Error:', error);
            }
        }
    </script>
</x-app-layout>
