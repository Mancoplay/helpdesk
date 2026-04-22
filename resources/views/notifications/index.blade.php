@extends('layouts.app')

@section('title', 'Notificaciones')
@section('header', 'Notificaciones')
@section('show_back_button', '1')
@section('back_url', route('dashboard'))

@section('content')
    @include('notifications.partials.history-card', ['notifications' => $notifications])
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const historyCardSelector = '.js-notifications-history-card';
            let refreshInFlight = false;

            const refreshNotificationsHistory = function () {
                const historyCard = document.querySelector(historyCardSelector);
                if (!historyCard || refreshInFlight) {
                    return;
                }

                refreshInFlight = true;

                fetch(window.location.href, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                    cache: 'no-store',
                })
                    .then(function (response) {
                        if (!response.ok) {
                            return null;
                        }

                        return response.text();
                    })
                    .then(function (html) {
                        if (!html) {
                            return;
                        }

                        const parser = new DOMParser();
                        const doc = parser.parseFromString(html, 'text/html');
                        const nextHistoryCard = doc.querySelector(historyCardSelector);
                        const currentHistoryCard = document.querySelector(historyCardSelector);

                        if (nextHistoryCard && currentHistoryCard) {
                            currentHistoryCard.replaceWith(nextHistoryCard);
                        }
                    })
                    .catch(function () {
                        // Ignore transient failures and keep the current history visible.
                    })
                    .finally(function () {
                        refreshInFlight = false;
                    });
            };

            window.addEventListener('helpdesk:notifications-updated', function () {
                refreshNotificationsHistory();
            });
        });
    </script>
@endpush
