import { createContext, useContext } from 'react';
import type { PropsWithChildren } from 'react';
import { useVideoPlayer } from 'expo-video';
import type { VideoPlayer } from 'expo-video';

const dayVideo = require('../../assets/videos/day-aerial.mp4');
const nightVideo = require('../../assets/videos/night-aerial.mp4');

type HeroVideoContextValue = {
  dayPlayer: VideoPlayer;
  nightPlayer: VideoPlayer;
};

const HeroVideoContext = createContext<HeroVideoContextValue | null>(null);

export function HeroVideoProvider({ children }: PropsWithChildren) {
  const dayPlayer = useVideoPlayer(dayVideo, (p) => {
    p.loop = true;
    p.muted = true;
    p.play();
  });
  const nightPlayer = useVideoPlayer(nightVideo, (p) => {
    p.loop = true;
    p.muted = true;
    p.play();
  });

  return (
    <HeroVideoContext.Provider value={{ dayPlayer, nightPlayer }}>
      {children}
    </HeroVideoContext.Provider>
  );
}

export function useHeroVideo() {
  const ctx = useContext(HeroVideoContext);
  if (!ctx) throw new Error('useHeroVideo must be used within HeroVideoProvider');
  return ctx;
}
