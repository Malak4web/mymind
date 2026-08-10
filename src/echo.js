import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

window.Pusher = Pusher

let echoInstance = null

/**
 * Initialize Laravel Echo with Pusher.
 * @param {string} token - The user's Bearer token for private channel auth
 * @param {string} apiBase - The API base URL (e.g. https://mind.zadians.com/api)
 * @returns {Echo} The Echo instance
 */
export function initEcho(token, apiBase) {
  if (echoInstance) {
    echoInstance.disconnect()
  }

  // Derive the broadcast auth endpoint from apiBase
  const authEndpoint = `${apiBase}/broadcasting/auth`

  echoInstance = new Echo({
    broadcaster: 'pusher',
    key: '7adfbdb785782270cb45',
    cluster: 'mt1',
    forceTLS: true,
    authEndpoint,
    auth: {
      headers: {
        Authorization: `Bearer ${token}`,
        Accept: 'application/json'
      }
    }
  })

  return echoInstance
}

/**
 * Get the current Echo instance.
 * @returns {Echo|null}
 */
export function getEcho() {
  return echoInstance
}

/**
 * Disconnect and destroy the Echo instance.
 */
export function disconnectEcho() {
  if (echoInstance) {
    echoInstance.disconnect()
    echoInstance = null
  }
}
