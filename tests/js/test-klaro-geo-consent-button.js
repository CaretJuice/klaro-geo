/**
 * js/klaro-geo-consent-button.js: opening the consent modal from
 * .open-klaro-modal elements, and the floating button.
 *
 * The script uses no jQuery, so none is defined here: a reference to it
 * would throw.
 */

describe('Consent button', function() {
    // The script adds one document listener each time it is loaded; they are
    // removed after each test so a click is handled once.
    let listeners;
    let addEventListener;

    function load(settings) {
        window.klaroGeo = Object.assign({
            enableFloatingButton: false,
            floatingButtonText: 'Manage Consent',
            floatingButtonTheme: 'light',
            floatingButtonPosition: 'bottom-right'
        }, settings || {});
        jest.resetModules();
        require('../../js/klaro-geo-consent-button.js');
    }

    function click(element) {
        const event = new MouseEvent('click', { bubbles: true, cancelable: true });
        element.dispatchEvent(event);
        return event;
    }

    beforeEach(function() {
        document.body.innerHTML =
            '<ul><li><a href="#" class="open-klaro-modal"><span id="inner">Manage Consent</span></a></li>' +
            '<li><a href="#" id="other">About</a></li></ul>';
        window.klaro = { show: jest.fn() };
        global.klaroGeoError = jest.fn();

        listeners = [];
        addEventListener = document.addEventListener;
        document.addEventListener = function(type, handler) {
            listeners.push([type, handler]);
            return addEventListener.apply(document, arguments);
        };
    });

    afterEach(function() {
        document.addEventListener = addEventListener;
        listeners.forEach(function(l) { document.removeEventListener(l[0], l[1]); });
        document.body.innerHTML = '';
        delete window.klaro;
        delete window.klaroGeo;
    });

    test('a click on an .open-klaro-modal element opens the modal and is not followed', function() {
        load();

        const event = click(document.querySelector('.open-klaro-modal'));

        expect(window.klaro.show).toHaveBeenCalledTimes(1);
        expect(event.defaultPrevented).toBe(true);
    });

    test('a click on an element inside it counts', function() {
        load();

        click(document.getElementById('inner'));

        expect(window.klaro.show).toHaveBeenCalledTimes(1);
    });

    test('other clicks are left alone', function() {
        load();

        const event = click(document.getElementById('other'));

        expect(window.klaro.show).not.toHaveBeenCalled();
        expect(event.defaultPrevented).toBe(false);
    });

    test('an element added after the script ran also opens the modal', function() {
        load();
        const late = document.createElement('button');
        late.className = 'open-klaro-modal';
        document.body.appendChild(late);

        click(late);

        expect(window.klaro.show).toHaveBeenCalledTimes(1);
    });

    test.each([
        ['Klaro did not load', undefined],
        ['Klaro has no show()', {}]
    ])('reports an error when %s', function(name, klaro) {
        window.klaro = klaro;
        load();

        const event = click(document.querySelector('.open-klaro-modal'));

        expect(event.defaultPrevented).toBe(true);
        expect(global.klaroGeoError).toHaveBeenCalledWith('Klaro is not initialized or show() function is missing.');
    });

    test('no floating button unless it is enabled', function() {
        load();

        expect(document.querySelector('.klaro-floating-button')).toBeNull();
    });

    test('the floating button uses the text, theme and position settings and opens the modal', function() {
        load({
            enableFloatingButton: true,
            floatingButtonText: 'Privacy <b>Settings</b>',
            floatingButtonTheme: 'blue',
            floatingButtonPosition: 'top-left'
        });

        const buttons = document.querySelectorAll('.klaro-floating-button');
        expect(buttons).toHaveLength(1);
        expect(buttons[0].className).toBe('klaro-floating-button open-klaro-modal klaro-theme-blue klaro-position-top-left');
        // Inserted as text, not HTML
        expect(buttons[0].textContent).toBe('Privacy <b>Settings</b>');
        expect(buttons[0].querySelector('b')).toBeNull();
        expect(buttons[0].getAttribute('aria-label')).toBe('Privacy <b>Settings</b>');

        click(buttons[0]);
        expect(window.klaro.show).toHaveBeenCalledTimes(1);
    });
});
