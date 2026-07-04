import dayGridPlugin from '@fullcalendar/daygrid';
import interactionPlugin from '@fullcalendar/interaction';
import FullCalendar from '@fullcalendar/react';
import timeGridPlugin from '@fullcalendar/timegrid';
import type { EventContentArg, EventInput } from '@fullcalendar/core';
import { useMemo } from 'react';
import { useNavigate } from 'react-router-dom';

import type { BookingCalendarEvent } from '@/lib/bookings';

const statusColor: Record<string, string> = {
  pending: '#FFB800',
  needs_discussion: '#38bdf8',
  accepted: '#22c55e',
  completed: '#ffffff',
  cancelled: '#ff1f1f',
  declined: '#777777',
  expired: '#777777',
};

export function BookingCalendar({ events }: { events: BookingCalendarEvent[] }) {
  const navigate = useNavigate();
  const calendarEvents = useMemo<EventInput[]>(() => events
    .filter((event) => event.start && event.end)
    .map((event) => ({
      id: event.id,
      title: event.title,
      start: event.start!,
      end: event.end!,
      extendedProps: event.extendedProps,
      backgroundColor: '#111111',
      borderColor: statusColor[event.extendedProps.status] ?? '#ff1f1f',
      textColor: '#ffffff',
    })), [events]);

  function renderEvent(info: EventContentArg) {
    const status = String(info.event.extendedProps.status ?? '').replace(/_/g, ' ');

    return (
      <div className="overflow-hidden px-1 py-0.5">
        <p className="truncate text-[11px] font-bold uppercase text-white">{info.event.title}</p>
        <p className="truncate text-[9px] uppercase tracking-widest text-[#bbbbbb]">{status}</p>
      </div>
    );
  }

  return (
    <div className="booking-calendar border border-[#2a2a2a] bg-[#111111] p-4 text-white">
      <style>{`
        .booking-calendar .fc {
          color: #ffffff;
          font-family: inherit;
        }
        .booking-calendar .fc-theme-standard td,
        .booking-calendar .fc-theme-standard th,
        .booking-calendar .fc-theme-standard .fc-scrollgrid {
          border-color: #2a2a2a;
        }
        .booking-calendar .fc-toolbar-title {
          font-family: var(--font-heading);
          text-transform: uppercase;
        }
        .booking-calendar .fc-button {
          background: #ff1f1f;
          border-color: #ff1f1f;
          border-radius: 0;
          font-family: var(--font-heading);
          font-size: 11px;
          letter-spacing: .12em;
          text-transform: uppercase;
        }
        .booking-calendar .fc-button:disabled {
          background: #333333;
          border-color: #333333;
        }
        .booking-calendar .fc-daygrid-day-number,
        .booking-calendar .fc-col-header-cell-cushion {
          color: #dddddd;
        }
        .booking-calendar .fc-event {
          border-radius: 0;
          cursor: pointer;
        }
      `}</style>
      <FullCalendar
        plugins={[dayGridPlugin, timeGridPlugin, interactionPlugin]}
        initialView="dayGridMonth"
        headerToolbar={{
          left: 'prev,next today',
          center: 'title',
          right: 'dayGridMonth,timeGridWeek,timeGridDay',
        }}
        height="auto"
        events={calendarEvents}
        eventContent={renderEvent}
        eventClick={(info) => navigate(`/account/bookings/${info.event.id}`)}
      />
    </div>
  );
}
