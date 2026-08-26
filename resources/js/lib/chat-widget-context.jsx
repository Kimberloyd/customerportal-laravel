import { createContext, useCallback, useContext, useMemo, useState } from 'react';

const ChatWidgetContext = createContext(null);

const MAX_OPEN_CHATS = 2;

// A customer's conversation with Administrator is a different thread than
// their conversation with an Employee, so the two need distinct keys even
// though they share the same customerId. Facebook threads have their own
// identity entirely (no customerId in the same sense), keyed by thread id.
function chatKey(target) {
    if (target.channel === 'facebook') return `fb-${target.threadId}`;

    return target.staffUserId != null
        ? `${target.customerId}:staff-${target.staffUserId}`
        : String(target.customerId);
}

export function ChatWidgetProvider({ children }) {
    const [chats, setChats] = useState([]);
    // Bumped whenever a widget panel marks messages read, so the header badge
    // and Chats dropdown can refresh immediately instead of waiting for the
    // next poll or new-message event.
    const [readSignal, setReadSignal] = useState(0);
    const notifyRead = useCallback(() => setReadSignal((value) => value + 1), []);
    // Shared so the "new message" affordance in both the header dropdown and
    // the floating widget stack open the same compose modal.
    const [composeOpen, setComposeOpen] = useState(false);
    // ChatWidget renders as a sibling of <App>, outside Inertia's context, so
    // it can't call usePage() itself to know whether the visitor is signed
    // in. AuthenticatedLayout -- which only ever mounts on authenticated
    // pages -- reports its presence here instead.
    const [isAuthenticated, setIsAuthenticated] = useState(false);

    const openChat = useCallback((nextTarget) => {
        const key = chatKey(nextTarget);

        setChats((current) => {
            const withoutExisting = current.filter((chat) => chat.key !== key);
            const next = [...withoutExisting, { ...nextTarget, key, minimized: false }];

            return next.length > MAX_OPEN_CHATS
                ? next.slice(next.length - MAX_OPEN_CHATS)
                : next;
        });
    }, []);

    const closeChat = useCallback((key) => {
        setChats((current) => current.filter((chat) => chat.key !== key));
    }, []);

    const setChatMinimized = useCallback((key, minimized) => {
        setChats((current) =>
            current.map((chat) => (chat.key === key ? { ...chat, minimized } : chat)),
        );
    }, []);

    const value = useMemo(
        () => ({
            chats,
            openChat,
            closeChat,
            setChatMinimized,
            maxOpenChats: MAX_OPEN_CHATS,
            readSignal,
            notifyRead,
            composeOpen,
            setComposeOpen,
            isAuthenticated,
            setIsAuthenticated,
        }),
        [chats, openChat, closeChat, setChatMinimized, readSignal, notifyRead, composeOpen, isAuthenticated],
    );

    return (
        <ChatWidgetContext.Provider value={value}>
            {children}
        </ChatWidgetContext.Provider>
    );
}

export function useChatWidget() {
    const context = useContext(ChatWidgetContext);
    if (!context) {
        throw new Error('useChatWidget must be used within a ChatWidgetProvider');
    }
    return context;
}
