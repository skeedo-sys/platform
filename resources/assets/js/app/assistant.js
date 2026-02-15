'use strict';

import Alpine from 'alpinejs';
import api from './api';
import { toast } from '../base/toast';

export function assistantView() {
  Alpine.data('assistant', (assistant = null) => ({
    assistant: null,
    model: {
      visibility: 2,
    },
    isProcessing: false,
    resources: [],
    resourceIndex: 0,
    isDeleting: false,
    currentResource: null,
    isEditing: false,

    init() {
      this.assistant = assistant;
      this.model = { ...this.model, ...this.assistant };
    },

    async submit() {
      if (this.isProcessing) {
        return;
      }

      this.isProcessing = true;

      let data = { ...this.model };

      if (this.model.file) {
        data.avatar = await this.readFileAsBase64(this.model.file);
        delete data.file;
      }

      this.assistant ? this.update(data) : this.create(data);
    },

    update(data) {
      api
        .patch(`/assistants/${this.assistant.id}`, data)
        .then((response) => {
          this.assistant = response.data;
          this.model = { ...this.assistant };

          this.isProcessing = false;

          toast.success('Assistant has been updated successfully!');
        })
        .finally(() => {
          this.isProcessing = false;
          this.isEditing = false;
        });
    },

    create(data) {
      api
        .post('/assistants', data)
        .then((response) => {
          this.assistant = response.data;
          this.model = { ...this.assistant };

          let pendingResources = this.resources.filter(
            (r) => r.status === 'pending'
          );

          let url = new URL(window.location.href);
          url.pathname = '/app/assistants/' + this.assistant.id;
          url.search = '';
          window.history.pushState({}, '', url);

          if (pendingResources.length > 0) {
            toast.success(
              'Assistant created! Please wait while we train it with uploaded resources...'
            );

            this.processNextPendingResource();
          } else {
            toast.success('Assistant has been created successfully!');
            this.isProcessing = false;
            this.isEditing = false;
          }
        })
        .catch((error) => (this.isProcessing = false));
    },

    remove() {
      if (this.isProcessing) {
        return;
      }

      this.isProcessing = true;

      api
        .delete(`/assistants/${this.assistant.id}`)
        .then(() => {
          toast.defer('Assistant has been deleted successfully!');
          window.location.href = '/app/assistants';
        })
        .catch((error) => {
          window.modal.close();
          toast.error('Failed to delete assistant');
          this.isProcessing = false;
        });
    },

    readFileAsBase64(file) {
      return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = function (e) {
          const base64String = e.target.result.split(',')[1];
          resolve(base64String);
        };
        reader.onerror = function (error) {
          reject(error);
        };
        reader.readAsDataURL(file);
      });
    },

    /**
     * Adds selected files to the resources array
     * @param {File[]} files - Array of File objects selected by file input
     */
    pushFiles(files) {
      for (let i = 0; i < files.length; i++) {
        const file = files[i];
        if (
          !this.resources.some((r) => r.type === 'file' && r.name === file.name)
        ) {
          this.resources.push({
            type: 'file',
            blob: file,
            name: file.name,
            mimeType: file.type,
            extension: file.name.split('.').pop(),
            size: file.size,
            lastModified: file.lastModified,
            status: 'pending',
            error: null,
            id: this.resourceIndex++,
          });
        }
      }

      this.processNextPendingResource();
    },

    processNextPendingResource() {
      if (!this.assistant) {
        return;
      }

      // First, process all pending files
      const pendingFile = this.resources.find(
        (r) => r.type === 'file' && r.status === 'pending'
      );
      const isFileUploading = this.resources.some(
        (r) => r.type === 'file' && r.status === 'uploading'
      );

      if (pendingFile && !isFileUploading) {
        this.uploadResource(pendingFile);
        return;
      }

      // If no files are pending or uploading, process pending pages
      if (!pendingFile && !isFileUploading) {
        const pendingPage = this.resources.find(
          (r) => r.type === 'page' && r.status === 'pending'
        );
        const isPageUploading = this.resources.some(
          (r) => r.type === 'page' && r.status === 'uploading'
        );

        if (pendingPage && !isPageUploading) {
          this.uploadResource(pendingPage);
          return;
        }
      }

      this.isProcessing = false;
      this.isEditing = false;
    },

    uploadResource(resource) {
      resource.status = 'uploading';

      let data;
      if (resource.type === 'file') {
        data = new FormData();
        data.append('file', resource.blob);
      } else {
        data = { url: resource.url };
      }

      api
        .post(`/assistants/${this.assistant.id}/dataset`, data)
        .then((response) => {
          this.assistant.dataset.push(response.data);
          this.removeResource(resource);
        })
        .catch((error) => {
          resource.status = 'error';
          resource.error = error.message;
        })
        .finally(() => this.processNextPendingResource());
    },

    removeResource(resource) {
      this.resources = this.resources.filter((r) => r !== resource);
    },

    deleteResource(resource) {
      this.isDeleting = true;

      api
        .delete(`/assistants/${this.assistant.id}/dataset/${resource.id}`)
        .then(() => {
          this.assistant.dataset.splice(
            this.assistant.dataset.indexOf(resource),
            1
          );
          window.modal.close();

          this.currentResource = null;
          toast.show('Data unit has been deleted successfully.', 'ti ti-trash');

          this.isDeleting = false;
        })
        .catch((error) => (this.isDeleting = false));
    },

    addPage(url) {
      // Add page to queue (even if assistant doesn't exist yet)
      if (!this.resources.some((r) => r.type === 'page' && r.url === url)) {
        this.resources.push({
          type: 'page',
          url: url,
          status: 'pending',
          error: null,
          id: this.resourceIndex++,
        });

        this.$refs.url.value = '';
      }

      window.modal.close();
      this.processNextPendingResource();
    },
  }));

  Alpine.data('assistants', () => ({
    limit: 5,
    fetched: false,
    list: {
      private: {
        hasMore: true,
        isLoading: false,
        resources: [],
      },
      team: {
        hasMore: true,
        isLoading: false,
        resources: [],
      },
      community: {
        hasMore: true,
        isLoading: false,
        resources: [],
      },
      system: {
        hasMore: true,
        isLoading: false,
        resources: [],
      },
    },
    showSearchResults: false,

    init() {
      this.getResources();

      let timer = null;
      window.addEventListener('lc.filtered', (e) => {
        clearTimeout(timer);
        timer = setTimeout(() => {
          this.showSearchResults = e.detail.query !== '' && e.detail.query !== null;
        }, 200);
      });
    },

    loadMore(scope) {
      let cursor = null;
      let list = null;

      if (scope === 'private') {
        list = this.list.private;
        list.isLoading = true;
      } else if (scope === 'team') {
        list = this.list.team;
        list.isLoading = true;
      } else if (scope === 'community') {
        list = this.list.community;
        list.isLoading = true;
      } else if (scope === 'system') {
        list = this.list.system;
        list.isLoading = true;
      }

      if (list && list.hasMore) {
        cursor = list.resources[list.resources.length - 1].id;
        this.getResources({ scope, cursor }).finally(() => {
          list.isLoading = false;
        });
      }
    },

    getResources({ scope = null, cursor = null } = {}) {
      let data = {
        limit: this.limit,
        group: true
      };

      if (scope) {
        data.scope = scope;
      }

      if (cursor) {
        data.starting_after = cursor;
      }

      return api.get('/assistants', data)
        .then(response => response.json())
        .then(list => {
          this.fetched = true;
          let data = list.data;

          if (data.private) {
            this.list.private.resources.push(...data.private);
            this.list.private.hasMore = data.private.length === this.limit;
          }

          if (data.team) {
            this.list.team.resources.push(...data.team);
            this.list.team.hasMore = data.team.length === this.limit;
          }

          if (data.community) {
            this.list.community.resources.push(...data.community);
            this.list.community.hasMore = data.community.length === this.limit;
          }

          if (data.system) {
            this.list.system.resources.push(...data.system);
            this.list.system.hasMore = data.system.length === this.limit;
          }
        });
    },

    deleteResource(resource) {
      this.isDeleting = true;

      api.delete(`/assistants/${resource.id}`)
        .then(() => {
          let lists = ['private', 'team', 'community', 'system'];
          lists.forEach(list => {
            this.list[list].resources = this.list[list].resources.filter(r => r.id !== resource.id);
          });

          window.modal.close();

          this.currentResource = null;
          toast.show('Assistant has been deleted successfully.', 'ti ti-trash')

          this.isDeleting = false;
        })
        .catch(error => { this.isDeleting = false; });
    },
  }));
}
