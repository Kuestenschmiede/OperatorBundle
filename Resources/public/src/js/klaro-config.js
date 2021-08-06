window.klaroConfig ={
  translations: {
    en: {
      youtubeVideo: {
        title: "YouTube video display",
        description: "Allows display of a video from YouTube"
      },
      vimeoVideo: {
        title: "Vimeo Video-Darstellung",
        description: "Allows display of a video from Vimeo"
      },
      purposes: {
        videoDisplay: "Video display"
      }
    },
    de: {
      youtubeVideo: {
        title: "YouTube Video-Darstellung",
        description: "Erlaubt die Darstellung eines Videos von YouTube"
      },
      vimeoVideo: {
        title: "Vimeo Video-Darstellung",
        description: "Erlaubt die Darstellung eines Videos von Vimeo"
      },
      purposes: {
        videoDisplay: "Anzeige von Videos"
      }
    }
  },
  services: [
    {
      name: "youtubeVideo",
      purposes: ["videoDisplay"]
    },
    {
      name: "vimeoVideo",
      purposes: ["videoDisplay"]
    },
  ],
};