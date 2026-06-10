<x-app-layout>
    <div class="py-6" x-data="documentsApp()">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-semibold text-gray-800">文件管理</h2>
            </div>

            <!-- Upload Form -->
            <div class="bg-white p-6 rounded-lg shadow mb-6">
                <h3 class="text-lg font-medium mb-4">上傳文件</h3>
                <form @submit.prevent="uploadDocument()" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">標題</label>
                            <input x-model="form.title" type="text" required
                                   class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">來源類型</label>
                            <select x-model="form.source_type"
                                    class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="file">檔案上傳</option>
                                <option value="url">網頁 URL</option>
                            </select>
                        </div>
                    </div>

                    <div x-show="form.source_type === 'file'">
                        <label class="block text-sm font-medium text-gray-700 mb-1">選擇檔案 (PDF, DOCX, TXT)</label>
                        <input type="file" @change="form.file = $event.target.files[0]" accept=".pdf,.docx,.txt"
                               class="w-full border border-gray-300 rounded-lg p-2">
                    </div>

                    <div x-show="form.source_type === 'url'">
                        <label class="block text-sm font-medium text-gray-700 mb-1">URL</label>
                        <input x-model="form.url" type="url" placeholder="https://..."
                               class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                    </div>

                    <button type="submit" :disabled="uploading"
                            class="bg-indigo-600 text-white px-6 py-2 rounded-lg hover:bg-indigo-700 disabled:opacity-50">
                        <span x-show="!uploading">上傳</span>
                        <span x-show="uploading">上傳中...</span>
                    </button>
                </form>
            </div>

            <!-- Documents List -->
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">標題</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">類型</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">狀態</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">日期</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">操作</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <template x-for="doc in documents" :key="doc.id">
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm" x-text="doc.title"></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <span class="px-2 py-1 rounded-full text-xs"
                                          :class="{
                                              'bg-blue-100 text-blue-800': doc.source_type === 'file',
                                              'bg-green-100 text-green-800': doc.source_type === 'url',
                                              'bg-purple-100 text-purple-800': doc.source_type === 'database'
                                          }"
                                          x-text="doc.source_type"></span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <span class="px-2 py-1 rounded-full text-xs"
                                          :class="{
                                              'bg-yellow-100 text-yellow-800': doc.status === 'pending',
                                              'bg-blue-100 text-blue-800': doc.status === 'processing',
                                              'bg-green-100 text-green-800': doc.status === 'completed',
                                              'bg-red-100 text-red-800': doc.status === 'failed'
                                          }"
                                          x-text="doc.status"></span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"
                                    x-text="new Date(doc.created_at).toLocaleDateString()"></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <button @click="deleteDocument(doc.id)"
                                            class="text-red-600 hover:text-red-800">刪除</button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
                <div x-show="documents.length === 0" class="p-8 text-center text-gray-500">
                    尚無文件，請上傳第一份文件。
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
    function documentsApp() {
        return {
            documents: [],
            uploading: false,
            form: {
                title: '',
                source_type: 'file',
                file: null,
                url: '',
            },

            async init() {
                await this.loadDocuments();
            },

            async loadDocuments() {
                const res = await fetch('/api/documents', {
                    headers: {'Accept': 'application/json'},
                });
                const data = await res.json();
                this.documents = data.data || data;
            },

            async uploadDocument() {
                this.uploading = true;
                const formData = new FormData();
                formData.append('title', this.form.title);
                formData.append('source_type', this.form.source_type);

                if (this.form.source_type === 'file' && this.form.file) {
                    formData.append('file', this.form.file);
                } else if (this.form.source_type === 'url') {
                    formData.append('url', this.form.url);
                }

                try {
                    const res = await fetch('/api/documents', {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        },
                        body: formData,
                    });
                    const doc = await res.json();
                    this.documents.unshift(doc);
                    this.form = {title: '', source_type: 'file', file: null, url: ''};
                } catch (e) {
                    alert('上傳失敗');
                }
                this.uploading = false;
            },

            async deleteDocument(id) {
                if (!confirm('確定要刪除此文件？')) return;
                await fetch(`/api/documents/${id}`, {
                    method: 'DELETE',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    },
                });
                this.documents = this.documents.filter(d => d.id !== id);
            }
        }
    }
    </script>
    @endpush
</x-app-layout>
