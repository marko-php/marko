/**
 * marko/authentication-passkey browser helper.
 *
 * Turns the options JSON from PasskeyRegistration::creationOptions() or
 * PasskeyAuthentication::requestOptions() into a navigator.credentials call,
 * and the resulting credential into the JSON that register(), authenticate()
 * and confirm() accept. No framework, no dependencies: post the returned
 * object however your app posts JSON.
 *
 *   import { createPasskey, getPasskey } from './passkeys.js';
 *
 *   const options = await (await fetch('/passkeys/options', { method: 'POST' })).json();
 *   const credential = await createPasskey(options);
 *   await fetch('/passkeys', { method: 'POST', body: JSON.stringify({ name, credential }) });
 */

const toBytes = (value) => {
    const base64 = value.replace(/-/g, '+').replace(/_/g, '/');
    const padded = base64 + '='.repeat((4 - (base64.length % 4)) % 4);

    return Uint8Array.from(atob(padded), (character) => character.charCodeAt(0));
};

const toBase64Url = (buffer) => btoa(String.fromCharCode(...new Uint8Array(buffer)))
    .replace(/\+/g, '-')
    .replace(/\//g, '_')
    .replace(/=+$/, '');

const descriptors = (list = []) => list.map((descriptor) => ({ ...descriptor, id: toBytes(descriptor.id) }));

const creationOptionsFromJson = (options) => (
    typeof PublicKeyCredential.parseCreationOptionsFromJSON === 'function'
        ? PublicKeyCredential.parseCreationOptionsFromJSON(options)
        : {
            ...options,
            challenge: toBytes(options.challenge),
            user: { ...options.user, id: toBytes(options.user.id) },
            excludeCredentials: descriptors(options.excludeCredentials),
        }
);

const requestOptionsFromJson = (options) => (
    typeof PublicKeyCredential.parseRequestOptionsFromJSON === 'function'
        ? PublicKeyCredential.parseRequestOptionsFromJSON(options)
        : {
            ...options,
            challenge: toBytes(options.challenge),
            allowCredentials: descriptors(options.allowCredentials),
        }
);

const credentialToJson = (credential) => {
    if (typeof credential.toJSON === 'function') {
        return credential.toJSON();
    }

    const { response } = credential;
    const json = {
        id: credential.id,
        rawId: toBase64Url(credential.rawId),
        type: credential.type,
        authenticatorAttachment: credential.authenticatorAttachment ?? null,
        clientExtensionResults: credential.getClientExtensionResults(),
        response: { clientDataJSON: toBase64Url(response.clientDataJSON) },
    };

    if (response.attestationObject) {
        json.response.attestationObject = toBase64Url(response.attestationObject);
        json.response.transports = response.getTransports?.() ?? [];
    } else {
        json.response.authenticatorData = toBase64Url(response.authenticatorData);
        json.response.signature = toBase64Url(response.signature);
        json.response.userHandle = response.userHandle ? toBase64Url(response.userHandle) : null;
    }

    return json;
};

/**
 * Create a passkey from creation options; resolves to the credential JSON for register().
 */
export async function createPasskey(options) {
    const credential = await navigator.credentials.create({ publicKey: creationOptionsFromJson(options) });

    return credentialToJson(credential);
}

/**
 * Sign in with a passkey from request options; resolves to the credential JSON for
 * authenticate() or confirm(). Pass { conditional: true } to offer passkeys in the
 * browser's autofill (the username input needs autocomplete="username webauthn").
 */
export async function getPasskey(options, { conditional = false, signal } = {}) {
    const credential = await navigator.credentials.get({
        publicKey: requestOptionsFromJson(options),
        mediation: conditional ? 'conditional' : 'optional',
        signal,
    });

    return credentialToJson(credential);
}

/**
 * Whether this browser can use passkeys at all.
 */
export function passkeysSupported() {
    return typeof window !== 'undefined' && typeof window.PublicKeyCredential === 'function';
}
