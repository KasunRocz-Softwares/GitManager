<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Repositories') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <div id="flash-message" class="mt-6"></div>
                    <!-- Repository creation form -->
                    <form id="repository-form" class="mt-6 space-y-6">
                        @csrf
                        <div>
                            <x-input-label for="project_id" :value="__('Project')" />
                            <select id="project_id" name="project_id" class="mt-1 block w-full text-black" required>
                                <option value="" style="color: black">Select a Project</option>
                            </select>
                        </div>

                        <div>
                            <x-input-label for="name" :value="__('Repository Name')" />
                            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" required autofocus />
                        </div>

                        <div>
                            <x-input-label for="url" :value="__('Repository Path')" />
                            <x-text-input id="url" name="repo_path" type="text" class="mt-1 block w-full" required />
                        </div>

                        <div>
                            <x-input-label for="code_base_type" :value="__('Code Base Type')" />
                            <select id="code_base_type" name="code_base_type" class="mt-1 block w-full text-black" required>
                                <option value="Laravel" selected>Laravel</option>
                                <option value="NodeJS">NodeJS</option>
                                <option value="React/Vue">React/Vue</option>
                            </select>
                        </div>

                        <div id="dist_folder_container" style="display: none;">
                            <div class="flex items-center">
                                <input id="has_dist_folder" name="has_dist_folder" type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" value="1">
                                <label for="has_dist_folder" class="ml-2 block text-sm text-gray-900 dark:text-gray-100">
                                    {{ __('Upload dist folder and push to server') }}
                                </label>
                            </div>
                        </div>

                        <div class="flex items-center gap-4">
                            <x-primary-button type="button" onclick="submitForm()">{{ __('Save') }}</x-primary-button>
                        </div>
                    </form>

                    <!-- Existing repositories list -->
                    <div class="mt-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                            {{ __('Existing Repositories') }}
                        </h3>

                        <ul id="repositories-list" class="mt-4 space-y-2">
                            <!-- Repositories will be loaded here by JavaScript -->
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            fetchProjects();
            fetchRepositories();

            // Handle code base type change
            document.getElementById('code_base_type').addEventListener('change', function() {
                const codeBaseType = this.value;
                const distFolderContainer = document.getElementById('dist_folder_container');

                if (codeBaseType === 'NodeJS' || codeBaseType === 'React/Vue') {
                    distFolderContainer.style.display = 'block';
                } else {
                    distFolderContainer.style.display = 'none';
                    document.getElementById('has_dist_folder').checked = false;
                }
            });

            // Handle form submission
            window.submitForm = async () => {
                const form = document.getElementById('repository-form');
                const formData = new FormData(form);

                try {
                    const response = await fetch('{{ route('repositories.store') }}', {
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
                        addRepositoryToList(result);
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

            async function fetchProjects() {
                try {
                    const response = await fetch('{{ url('/api/projects')}}');
                    const projects = await response.json();

                    const projectSelect = document.getElementById('project_id');
                    projectSelect.innerHTML = `<option value="">Select a Project</option>`;
                    projects.forEach(project => {
                        const option = document.createElement('option');
                        option.value = project.id;
                        option.textContent = project.name;
                        projectSelect.appendChild(option);
                    });

                    // Add event listener to filter repositories by selected project
                    projectSelect.addEventListener('change', filterRepositories);
                } catch (error) {
                    console.error('Error:', error);
                }
            }

            async function fetchRepositories() {
                try {
                    const response = await fetch('{{ route('repositories.index') }}');
                    const repositories = await response.json();
                    window.allRepositories = repositories;
                    filterRepositories();
                } catch (error) {
                    console.error('Error:', error);
                }
            }

            function filterRepositories() {
                const selectedProjectId = document.getElementById('project_id').value;
                const list = document.getElementById('repositories-list');
                const filteredRepositories = window.allRepositories.filter(repo => repo.project_id == selectedProjectId);

                list.innerHTML = filteredRepositories.map(repository => `
                    <li class="border p-4 rounded">
                        <h4 class="font-semibold text-gray-800 dark:text-gray-200">${repository.name}</h4>
                        <p class="text-gray-600 dark:text-gray-400">${repository.repo_path}</p>
                        <p class="text-gray-600 dark:text-gray-400">Type: ${repository.code_base_type || 'Laravel'}</p>
                        ${(repository.code_base_type === 'NodeJS' || repository.code_base_type === 'React/Vue') && repository.has_dist_folder ?
                            `<p class="text-green-600 dark:text-green-400">Dist folder enabled</p>` : ''}
                    </li>
                `).join('');
            }

            function addRepositoryToList(repository) {
                const list = document.getElementById('repositories-list');
                if (document.getElementById('project_id').value == repository.project_id) {
                    list.innerHTML += `
                        <li class="border p-4 rounded">
                            <h4 class="font-semibold text-gray-800 dark:text-gray-200">${repository.name}</h4>
                            <p class="text-gray-600 dark:text-gray-400">${repository.repo_path}</p>
                            <p class="text-gray-600 dark:text-gray-400">Type: ${repository.code_base_type || 'Laravel'}</p>
                            ${(repository.code_base_type === 'NodeJS' || repository.code_base_type === 'React/Vue') && repository.has_dist_folder ?
                                `<p class="text-green-600 dark:text-green-400">Dist folder enabled</p>` : ''}
                        </li>
                    `;
                }
            }
        });
    </script>
</x-app-layout>
