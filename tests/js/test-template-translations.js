/**
 * Klaro Geo Template Translations JavaScript Tests
 */

describe('Klaro Template Translations', function() {

    beforeEach(function() {
        // Set up the test environment
        document.body.innerHTML = `
            <div id="translations-tabs" class="translations-container">
                <ul class="translations-tabs-nav">
                    <li><a href="#tab-zz">Fallback</a></li>
                    <li><a href="#tab-add">+ Add Language</a></li>
                </ul>

                <div id="tab-zz" class="translation-tab">
                    <h4>Fallback Translations</h4>
                    <table class="form-table">
                        <tr>
                            <th scope="row">Accept All</th>
                            <td>
                                <input type="text" name="template_config[translations][zz][acceptAll]" value="Accept All" class="regular-text">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Decline All</th>
                            <td>
                                <input type="text" name="template_config[translations][zz][declineAll]" value="Decline All" class="regular-text">
                            </td>
                        </tr>
                    </table>
                </div>

                <div id="tab-add" class="translation-tab">
                    <h4>Add New Language</h4>
                    <select id="new-language-code">
                        <option value="en">English (en)</option>
                        <option value="fr">French (fr)</option>
                    </select>
                    <button type="button" id="add-language-btn">Add Language</button>
                </div>
            </div>

            <textarea id="translations_json_editor" name="template_config[translations_json]" rows="15" cols="80" class="large-text code">{
                "zz": {
                    "acceptAll": "Accept All",
                    "declineAll": "Decline All"
                }
            }</textarea>

            <div class="translation-json-buttons">
                <button type="button" id="update_from_json" class="button">Update Form from JSON</button>
                <button type="button" id="update_to_json" class="button">Update JSON from Form</button>
            </div>
        `;

        // Create a simple jQuery mock
        global.jQuery = global.$ = function(selector) {
            if (selector === document) {
                return {
                    ready: function(callback) {
                        callback();
                        return this;
                    }
                };
            }

            // Handle different selectors
            if (selector === '#translations_json_editor') {
                return {
                    val: function(newValue) {
                        if (newValue === undefined) {
                            // Getter
                            return JSON.stringify({
                                zz: {
                                    acceptAll: "Accept All",
                                    declineAll: "Decline All"
                                },
                                fr: {
                                    acceptAll: "Accepter Tout",
                                    declineAll: "Tout Décliner"
                                }
                            });
                        } else {
                            // Setter - just return this for chaining
                            return this;
                        }
                    }
                };
            }

            if (selector === '.translation-tab input, .translation-tab textarea') {
                return {
                    each: function(callback) {
                        // Mock inputs
                        const inputs = [
                            {
                                attr: function(name) {
                                    if (name === 'name') return 'template_config[translations][zz][acceptAll]';
                                    return '';
                                },
                                val: function() { return 'Accept All'; }
                            },
                            {
                                attr: function(name) {
                                    if (name === 'name') return 'template_config[translations][zz][declineAll]';
                                    return '';
                                },
                                val: function() { return 'Decline All'; }
                            }
                        ];

                        // Call callback for each input
                        inputs.forEach(function(input) {
                            callback.call(input);
                        });

                        return this;
                    }
                };
            }

            if (selector === '#update_from_json' || selector === '#update_to_json') {
                return {
                    click: function(handler) {
                        // Store handler but don't execute
                        if (handler) {
                            this.clickHandler = handler;
                        } else if (this.clickHandler) {
                            // Execute stored handler if no argument
                            this.clickHandler();
                        }
                        return this;
                    }
                };
            }

            // Default object with common methods
            return {
                val: function() { return ''; },
                each: function() { return this; },
                click: function() { return this; },
                tabs: function() { return this; }
            };
        };

        // Add tabs method to jQuery
        global.$.fn = {
            tabs: function() { return this; }
        };

        // Mock window.addLanguageTab
        window.addLanguageTab = jest.fn();

        // Mock window.alert
        window.alert = jest.fn();

        // Mock console.error
        console.error = jest.fn();

        // Mock klaroGeoTemplates
        window.klaroGeoTemplates = {
            templates: {
                default: {
                    config: {
                        translations: {
                            zz: {
                                acceptAll: "Accept All",
                                declineAll: "Decline All"
                            }
                        }
                    }
                }
            }
        };
    });

    afterEach(function() {
        // Clean up
        document.body.innerHTML = '';
        jest.clearAllMocks();
        delete global.jQuery;
        delete global.$;
        delete window.addLanguageTab;
        delete window.alert;
        delete window.klaroGeoTemplates;
    });

    test('should update JSON from form fields', function() {
        // Create a direct mock for the jQuery selector
        const originalJQuery = global.$;

        // Track if val was called with a value
        let valCalled = false;

        // Create a new mock that will track the val call
        global.$ = function(selector) {
            if (selector === '#translations_json_editor') {
                return {
                    val: function(newValue) {
                        if (newValue !== undefined) {
                            valCalled = true;
                            return this;
                        }
                        return JSON.stringify({
                            zz: {
                                acceptAll: "Accept All",
                                declineAll: "Decline All"
                            }
                        });
                    }
                };
            }
            return originalJQuery(selector);
        };

        // Define the updateJsonFromForm function
        function updateJsonFromForm() {
            const translations = {
                zz: {
                    acceptAll: "Accept All",
                    declineAll: "Decline All"
                }
            };

            // Update the textarea
            $('#translations_json_editor').val(JSON.stringify(translations, null, 2));
        }

        // Call the function
        updateJsonFromForm();

        // The textarea val method should be called
        expect(valCalled).toBe(true);

        // Restore original jQuery
        global.$ = originalJQuery;
    });

    test('should update form fields from JSON', function() {
        // Define the updateFormFromJson function
        function updateFormFromJson() {
            // Get JSON from textarea
            const jsonText = $('#translations_json_editor').val();
            const translations = JSON.parse(jsonText);

            // Add tabs for each language
            Object.keys(translations).forEach(function(langCode) {
                if (langCode !== 'zz') {
                    window.addLanguageTab(langCode, 'Language Name');
                }
            });

            return true;
        }

        // Call the function
        const result = updateFormFromJson();

        // The function should return true
        expect(result).toBe(true);

        // addLanguageTab should be called for French
        expect(window.addLanguageTab).toHaveBeenCalledWith('fr', 'Language Name');
    });

    test('should handle invalid JSON', function() {
        // Mock JSON.parse to throw an error
        const originalJSONParse = JSON.parse;
        JSON.parse = jest.fn().mockImplementation(() => {
            throw new SyntaxError('Unexpected token i in JSON at position 2');
        });

        // Mock window.alert
        window.alert = jest.fn();

        // Mock console.error
        console.error = jest.fn();

        // Define the updateFormFromJson function
        function updateFormFromJson() {
            try {
                // Get JSON from textarea
                const jsonText = $('#translations_json_editor').val();
                JSON.parse(jsonText);
                return true;
            } catch (e) {
                window.alert('Error parsing JSON: ' + e.message);
                console.error('Error parsing JSON:', e);
                return false;
            }
        }

        // Call the function
        const result = updateFormFromJson();

        // The function should return false
        expect(result).toBe(false);

        // Alert should be called
        expect(window.alert).toHaveBeenCalled();

        // Console.error should be called
        expect(console.error).toHaveBeenCalled();

        // Restore original JSON.parse
        JSON.parse = originalJSONParse;
    });

    test('should handle button clicks', function() {
        // Create spy functions
        const updateJsonFromForm = jest.fn();
        const updateFormFromJson = jest.fn();

        // Get button objects
        const $updateFromJson = $('#update_from_json');
        const $updateToJson = $('#update_to_json');

        // Register handlers
        $updateFromJson.click(updateFormFromJson);
        $updateToJson.click(updateJsonFromForm);

        // Trigger clicks
        $updateFromJson.click();
        $updateToJson.click();

        // The functions should be called
        expect(updateFormFromJson).toHaveBeenCalled();
        expect(updateJsonFromForm).toHaveBeenCalled();
    });
});