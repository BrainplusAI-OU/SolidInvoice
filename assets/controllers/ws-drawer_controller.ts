import { Controller } from '@hotwired/stimulus';
import { Tab } from '@tabler/core';

/*
 * The floating bar on phones opens the navigation drawer on a given mode (Home or Settings),
 * the way Twenty's mobile bar does. The drawer itself is opened by Bootstrap's offcanvas data API;
 * this only switches the drawer's tab before it slides in.
 */
/* stimulusFetch: 'lazy' */
export default class extends Controller<HTMLElement> {
    mode(event: Event & { params: { mode?: string } }) {
        const tab = document.getElementById(`ws-mode-${event.params.mode ?? 'home'}`);

        if (tab) {
            (Tab.getOrCreateInstance(tab) as Tab).show();
        }
    }
}
