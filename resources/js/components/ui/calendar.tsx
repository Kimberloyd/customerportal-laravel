'use client'

import { ChevronLeft, ChevronRight } from 'lucide-react'
import {
  Calendar as CalendarPrimitive,
  CalendarCell,
  CalendarGrid,
  CalendarGridBody,
  CalendarGridHeader as CalendarGridHeaderPrimitive,
  CalendarHeaderCell,
  type CalendarProps as CalendarPrimitiveProps,
  type DateValue,
} from 'react-aria-components/Calendar'
import { Button } from 'react-aria-components/Button'
import { composeRenderProps } from 'react-aria-components/composeRenderProps'
import { Heading } from 'react-aria-components/Heading'
import { cn } from 'cn'

interface CalendarProps<T extends DateValue> extends CalendarPrimitiveProps<T> {
  className?: string
}

const Calendar = <T extends DateValue>({ className, ...props }: CalendarProps<T>) => {
  return (
    <CalendarPrimitive data-slot="calendar" {...props}>
      <CalendarHeader />
      <CalendarGrid>
        <CalendarGridHeader />
        <CalendarGridBody>
          {(date) => (
            <CalendarCell
              date={date}
              className={composeRenderProps(
                className,
                (className, { isSelected, isToday, isDisabled }) =>
                  cn(
                    'relative flex size-11 cursor-default items-center justify-center rounded-full text-sm text-foreground tabular-nums outline-none transition-colors hover:bg-muted',
                    isSelected && 'bg-primary text-primary-foreground hover:bg-primary/90',
                    isDisabled && 'pointer-events-none text-muted-foreground/40',
                    isToday &&
                      !isSelected &&
                      'after:pointer-events-none after:absolute after:bottom-1 after:left-1/2 after:z-10 after:size-1 after:-translate-x-1/2 after:rounded-full after:bg-primary',
                    className
                  )
              )}
            />
          )}
        </CalendarGridBody>
      </CalendarGrid>
    </CalendarPrimitive>
  )
}

const CalendarHeader = ({ className, ...props }: React.ComponentProps<'header'>) => {
  return (
    <header
      data-slot="calendar-header"
      className={cn('mb-4 flex items-center justify-between gap-1', className)}
      {...props}
    >
      <Button
        className="flex size-8 items-center justify-center rounded-full text-muted-foreground outline-none transition-colors hover:bg-muted hover:text-foreground disabled:pointer-events-none disabled:opacity-40 **:[svg]:size-4"
        slot="previous"
      >
        <ChevronLeft />
      </Button>
      <Heading className="text-base font-semibold text-foreground" />
      <Button
        className="flex size-8 items-center justify-center rounded-full text-muted-foreground outline-none transition-colors hover:bg-muted hover:text-foreground disabled:pointer-events-none disabled:opacity-40 **:[svg]:size-4"
        slot="next"
      >
        <ChevronRight />
      </Button>
    </header>
  )
}

const CalendarGridHeader = () => {
  return (
    <CalendarGridHeaderPrimitive>
      {(day) => (
        <CalendarHeaderCell className="pb-2 text-center text-sm font-medium text-muted-foreground">
          {day}
        </CalendarHeaderCell>
      )}
    </CalendarGridHeaderPrimitive>
  )
}

export type { CalendarProps }
export { Calendar, CalendarGridHeader, CalendarHeader }
