// One session-aware transport for forms, autosave, admin actions and uploads.
export function createSessionClient({fetcher, uploader, readToken, writeToken, holdUpload = () => () => {}}) {
    const publicActions = new Set(['login', 'register', 'forgot', 'reset', 'verify', 'temp_create', 'temp_add', 'temp_delete', 'temp_get', 'guest_limits', 'shared', 'cms_site', 'cms_page']);
    const boundaries = new Set(['login', 'register', 'logout', 'logout_all']);
    let generation = 0;
    let owner, identityBlocked = false, refreshPending = null;
    const id = value => value == null || value === '' ? null : String(value);
    const error = (message, status) => Object.assign(new Error(message), {status});
    const changed = () => error('Your sign-in changed or ended in another tab. Sign in again before saving; your unsent changes are still on this page.', 401);

    async function fetchJSON(action, {data = null, params = {}, signal} = {}) {
        const sentToken = readToken(), startedGeneration = generation;
        const opts = {credentials: 'same-origin', cache: 'no-store', signal, headers: {'X-CSRF-Token': sentToken || ''}};
        if (data !== null) {
            opts.method = 'POST';
            if (data instanceof FormData) opts.body = data;
            else {opts.headers['Content-Type'] = 'application/json'; opts.body = JSON.stringify(data);}
        }
        const response = await fetcher('api.php?' + new URLSearchParams({...params, action}), opts);
        let result;
        try {result = await response.json();}
        catch {throw error('The server returned an unexpected response. Please try again.', response.status);}
        if (!response.ok) throw error(result.error || 'Request failed.', response.status);
        return {result, sentToken, startedGeneration, csrf: response.headers.get('X-CSRF-Token'), user: response.headers.get('X-BOU-User')};
    }

    function accept(action, response) {
        const {result, csrf, user, sentToken} = response;
        if (action === 'session') {
            if (response.startedGeneration !== generation) throw changed();
            generation++;
            owner = id(result.user?.id);
            identityBlocked = false;
            writeToken(result.csrf);
        } else if (boundaries.has(action)) {
            generation++;
            // Deliberate authentication transitions return the new anonymous/user token.
            owner = user !== null ? id(user) : id(result.user?.id);
            identityBlocked = false;
            if (csrf) writeToken(csrf);
        } else {
            if (readToken() === sentToken && publicActions.has(action) && user !== null && owner !== undefined && id(user) !== owner) identityBlocked = true;
            if (!publicActions.has(action) && user !== null && owner !== undefined && id(user) !== owner) throw changed();
            // Do not let a late response overwrite a newer session's token.
            if (csrf && readToken() === sentToken && (!publicActions.has(action) || (user !== null && id(user) === owner))) writeToken(csrf);
        }
        return result;
    }

    async function recover(action) {
        const expectedOwner = owner, expectedGeneration = generation;
        if (!refreshPending) {
            refreshPending = fetchJSON('session').finally(() => {refreshPending = null;});
        }
        const fresh = await refreshPending;
        if (generation !== expectedGeneration) throw changed();
        const actualOwner = id(fresh.result.user?.id);
        const anonymousLogout = action === 'logout' && actualOwner === null;
        if (!publicActions.has(action) && !anonymousLogout && (expectedOwner === undefined || actualOwner === null || actualOwner !== expectedOwner)) throw changed();
        if (!fresh.result.csrf) throw error('Could not renew your session. Please try again.', 419);
        // Public forms can recover in an anonymous session. Protected writes cannot cross accounts.
        if (publicActions.has(action) && expectedOwner !== undefined && actualOwner !== expectedOwner) identityBlocked = true;
        writeToken(fresh.result.csrf);
    }

    async function withRecovery(action, send) {
        if (identityBlocked && action !== 'session' && !publicActions.has(action)) throw changed();
        try {return accept(action, await send());}
        catch (failure) {
            if (failure.status !== 419) throw failure;
            // The server rejects CSRF before running any action; retry only this rejection.
            await recover(action);
            try {return accept(action, await send());}
            catch (retryFailure) {
                if (retryFailure.status === 419) throw error('Your session changed again. Please submit once more; no action was completed.', 419);
                throw retryFailure;
            }
        }
    }

    return {
        request: (action, data = null) => withRecovery(action, () => fetchJSON(action, {data})),
        get: (action, params = {}, {signal} = {}) => withRecovery(action, () => fetchJSON(action, {params, signal})),
        upload: async (action, form, progress) => {
            const release = holdUpload();
            try {return await withRecovery(action, async () => {
            const sentToken = readToken();
            // Keep multipart's fallback token consistent with its HTTP header on a retry.
            if (form.has('_csrf')) form.set('_csrf', sentToken);
            const response = await uploader(action, form, sentToken, progress);
            return {...response, sentToken};
            });} finally {release();}
        },
    };
}

