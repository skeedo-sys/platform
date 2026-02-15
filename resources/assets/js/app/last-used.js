/**
 * LastUsedController - Manages last used items using localStorage with 
 * namespace support
 * 
 * This class provides functionality to save and retrieve the last used item for
 * different tools. Last used items are persisted in the browser's localStorage, 
 * allowing them to survive page refreshes and browser sessions. Each namespace 
 * maintains its own separate last used item.
 * 
 * @example
 * const lastUsedController = new LastUsedController();
 * 
 * // Save the last used model for chat
 * lastUsedController.setLastUsed('gpt-4', 'chat');
 * 
 * // Get the last used model for chat
 * const lastModel = lastUsedController.getLastUsed('chat');
 * 
 * // Save the last used model for image generation
 * lastUsedController.setLastUsed('dall-e-3', 'image');
 */
export class LastUsedController {
    /**
     * Creates a new LastUsedController instance
     */
    constructor() {
    }

    /**
     * Sets the last used item for the specified namespace
     * 
     * @param {string} item - The item to save as last used
     * @param {string} namespace - The namespace to save the item in
     * 
     * @example
     * lastUsedController.setLastUsed('gpt-4', 'chat');
     * lastUsedController.setLastUsed('dall-e-3', 'image');
     */
    setLastUsed(item, namespace) {
        const storageKey = this._getStorageKey(namespace);
        try {
            localStorage.setItem(storageKey, item);
        } catch (error) {
            console.error('Failed to save last used item to localStorage:', error);
        }
    }

    /**
     * Gets the last used item for the specified namespace
     * 
     * @param {string} namespace - The namespace to get the last used item from
     * @returns {string|null} The last used item, or null if none found
     * 
     * @example
     * const lastModel = lastUsedController.getLastUsed('chat');
     * // Returns 'gpt-4' if it was the last used model for chat
     */
    getLastUsed(namespace) {
        const storageKey = this._getStorageKey(namespace);
        try {
            return localStorage.getItem(storageKey);
        } catch (error) {
            console.warn('Failed to retrieve last used item from localStorage:', error);
            return null;
        }
    }

    /**
     * Removes the last used item for the specified namespace
     * 
     * @param {string} namespace - The namespace to clear
     * 
     * @example
     * lastUsedController.clearLastUsed('chat');
     */
    clearLastUsed(namespace) {
        const storageKey = this._getStorageKey(namespace);
        try {
            localStorage.removeItem(storageKey);
        } catch (error) {
            console.error('Failed to clear last used item from localStorage:', error);
        }
    }

    /**
     * Generates a storage key for the given namespace
     * 
     * @private
     * @param {string} namespace - The namespace identifier
     * @returns {string} The storage key for localStorage
     */
    _getStorageKey(namespace) {
        return `last_used_${namespace}`;
    }
}

export const lastUsed = new LastUsedController();
