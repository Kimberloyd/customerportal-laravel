'use client'

import {
  CalendarCell,
  CalendarGrid,
  CalendarGridBody,
  type DateValue,
  RangeCalendar as RangeCalendarPrimitive,
  type RangeCalendarProps as RangeCalendarPrimitiveProps,
} from 'react-aria-components/RangeCalendar'
import { composeRenderProps } from 'react-aria-components/composeRenderProps'
import { cn } from 'cn'
import { CalendarGridHeader, CalendarHeader } from './calendar'

interface RangeCalendarProps<T extends DateValue> extends RangeCalendarPrimitiveProps<T> {
  className?: string
  cellClassName?: string
}

const RangeCalendar = <T extends DateValue>({ className, cellClassName, ...props }: RangeCalendarProps<T>) => {
  return (
    <RangeCalendarPrimitive data-slot="range-calendar" className={cn('w-full', className)} {...props}>
      <CalendarHeader />
      <CalendarGrid className="w-full table-fixed">
        <CalendarGridHeader />
        <CalendarGridBody>
          {(date) => (
            <CalendarCell
              date={date}
              className={composeRenderProps(
                cellClassName,
                (className, { isSelected, isSelectionStart, isSelectionEnd, isToday, isDisabled, isOutsideMonth }) =>
                  cn(
                    'relative flex h-11 w-full cursor-default items-center justify-center text-sm text-foreground tabular-nums outline-none transition-colors',
                    isSelected &&
                      !isSelectionStart &&
                      !isSelectionEnd &&
                      'bg-primary/10 text-foreground',
                    (isSelectionStart || isSelectionEnd) &&
                      'rounded-full bg-primary text-primary-foreground',
                    isSelectionStart && !isSelectionEnd && 'rounded-r-none',
                    isSelectionEnd && !isSelectionStart && 'rounded-l-none',
                    !isSelected && 'rounded-full hover:bg-muted',
                    isOutsideMonth && 'text-muted-foreground/40',
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
    </RangeCalendarPrimitive>
  )
}

export type { RangeCalendarProps }
export { RangeCalendar }
